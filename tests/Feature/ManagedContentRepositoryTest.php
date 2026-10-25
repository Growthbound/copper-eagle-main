<?php

namespace Tests\Feature;

use App\Content\ContentLifecycle;
use App\Content\Enums\ContentKind;
use App\Content\Enums\ContentSource;
use App\Content\Enums\PageTemplate;
use App\Content\ManagedContentRepository;
use App\Content\ManagedPage;
use App\Content\Page;
use App\Content\PublicLinkTarget;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\IsolatesManagedContent;
use Tests\Concerns\WritesRecordFixtures;
use Tests\TestCase;

class ManagedContentRepositoryTest extends TestCase
{
    use IsolatesManagedContent;
    use RefreshDatabase;
    use WritesRecordFixtures;

    protected function tearDown(): void
    {
        $this->tearDownRecordFixtures();

        parent::tearDown();
    }

    public function test_draft_does_not_shadow_existing_json_page(): void
    {
        $user = User::factory()->create();
        app(ContentLifecycle::class)->createDraft(
            $this->identity('/'),
            $this->payload('Unpublished database homepage'),
            $user,
        );

        $resolved = app(ManagedContentRepository::class)->findPage('/');

        $this->assertInstanceOf(Page::class, $resolved);
        $this->assertSame($this->homepageTitle(), $resolved->title);
    }

    public function test_valid_published_revision_claims_path_before_json_fallback(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft(
            $this->identity('/'),
            $this->payload('Published database homepage'),
            $user,
        );
        $lifecycle->publish($entry, $entry->revisions()->sole(), 0, $user);

        $resolved = app(ManagedContentRepository::class)->findPage('/');

        $this->assertInstanceOf(ManagedPage::class, $resolved);
        $this->assertSame('Published database homepage', $resolved->title);
        $this->assertSame('hero/split', $resolved->blocks[0]->type);
    }

    public function test_cross_entry_revision_pointer_fails_closed_to_json(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $home = $lifecycle->createDraft(
            $this->identity('/'),
            $this->payload('Home database draft'),
            $user,
        );
        $other = $lifecycle->createDraft(
            $this->identity('other'),
            $this->payload('Other page'),
            $user,
        );
        $home->forceFill([
            'published_revision_id' => $other->revisions()->sole()->id,
        ])->save();

        $resolved = app(ManagedContentRepository::class)->findPage('/');

        $this->assertInstanceOf(Page::class, $resolved);
        $this->assertSame($this->homepageTitle(), $resolved->title);
    }

    public function test_unknown_path_without_public_database_or_json_content_is_missing(): void
    {
        $this->assertNull(
            app(ManagedContentRepository::class)->findPage('/not-a-real-page'),
        );
    }

    public function test_archived_adopted_page_tombstones_its_legacy_json_fallback(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft(
            $this->identity('/'),
            $this->payload('Adopted database homepage'),
            $user,
        );
        $entry = $lifecycle->publish($entry, $entry->revisions()->sole(), 0, $user);
        $footer = $lifecycle->createDraft([
            'kind' => ContentKind::Footer,
            'slug' => 'site-footer',
            'title' => 'Footer',
            'template' => PageTemplate::Standard,
        ], [
            'title' => 'Footer',
            'blocks' => [],
            'company' => [['label' => 'Services', 'href' => '/services']],
            'resources' => [['label' => 'Sitemap', 'href' => '/sitemap.xml']],
        ], $user);
        $lifecycle->publish($footer, $footer->revisions()->sole(), 0, $user);
        $lifecycle->archive($entry, 1, $user);
        // Something else to list, so the sitemap is not empty: an empty one
        // falls back to the site root on its own, which says nothing about
        // whether the tombstoned homepage was excluded.
        $service = $this->fixtureService();

        $this->assertNull(
            app(ManagedContentRepository::class)->findPage('/'),
        );
        $this->assertFalse(app(PublicLinkTarget::class)->exists('/'));
        $this->get('/')->assertNotFound();
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>http://localhost'.$service.'</loc>', false)
            ->assertDontSee('<loc>http://localhost</loc>', false);
    }

    public function test_every_formerly_published_slug_remains_tombstoned_after_a_rename(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft(
            $this->identity('/'),
            $this->payload('Adopted database homepage'),
            $user,
        );
        $entry = $lifecycle->publish($entry, $entry->revisions()->sole(), 0, $user);
        $footer = $lifecycle->createDraft([
            'kind' => ContentKind::Footer,
            'slug' => 'site-footer',
            'title' => 'Footer',
            'template' => PageTemplate::Standard,
        ], [
            'title' => 'Footer',
            'blocks' => [],
            'company' => [['label' => 'Services', 'href' => '/services']],
            'resources' => [['label' => 'Sitemap', 'href' => '/sitemap.xml']],
        ], $user);
        $lifecycle->publish($footer, $footer->revisions()->sole(), 0, $user);
        $renamed = $lifecycle->saveRevision(
            $entry,
            $this->payload('Renamed database homepage'),
            1,
            $user,
            ContentSource::Admin,
            identity: $this->identity('renamed-home'),
        );
        $lifecycle->publish($entry->fresh(), $renamed, 2, $user);
        $repository = app(ManagedContentRepository::class);

        $this->assertNull($repository->findPage('/'));
        $this->assertContains('/', $repository->claimedPageSlugs());
        $this->assertInstanceOf(ManagedPage::class, $repository->findPage('renamed-home'));
    }

    public function test_a_slug_first_published_by_rollback_remains_tombstoned_after_archive(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft(
            $this->identity('/'),
            $this->payload('Original unpublished homepage'),
            $user,
        );
        $original = $entry->revisions()->sole();
        $replacement = $lifecycle->saveRevision(
            $entry,
            $this->payload('First published homepage'),
            0,
            $user,
            ContentSource::Admin,
            identity: $this->identity('first-published-home'),
        );
        $entry = $lifecycle->publish($entry->fresh(), $replacement, 1, $user);
        $rollback = $lifecycle->rollback(
            $entry,
            $original,
            2,
            $user,
            ContentSource::Admin,
        );
        $entry = $entry->fresh();

        $this->assertSame('/', $entry->slug);
        $this->assertSame($rollback->id, $entry->published_revision_id);

        $navigation = $lifecycle->createDraft([
            'kind' => ContentKind::Navigation,
            'slug' => 'primary',
            'title' => 'Primary navigation',
            'template' => PageTemplate::Standard,
        ], [
            'title' => 'Primary navigation',
            'blocks' => [],
            'items' => [['label' => 'Services', 'href' => '/services']],
        ], $user);
        $lifecycle->publish($navigation, $navigation->revisions()->sole(), 0, $user);
        $footer = $lifecycle->createDraft([
            'kind' => ContentKind::Footer,
            'slug' => 'site-footer',
            'title' => 'Footer',
            'template' => PageTemplate::Standard,
        ], [
            'title' => 'Footer',
            'blocks' => [],
            'company' => [['label' => 'Services', 'href' => '/services']],
            'resources' => [['label' => 'Sitemap', 'href' => '/sitemap.xml']],
        ], $user);
        $lifecycle->publish($footer, $footer->revisions()->sole(), 0, $user);

        $lifecycle->archive($entry, 3, $user);

        $repository = app(ManagedContentRepository::class);
        $this->assertContains('/', $repository->claimedPageSlugs());
        $this->assertNull($repository->findPage('/'));
    }

    public function test_catalog_media_references_are_resolved_before_managed_blocks_render(): void
    {
        $user = User::factory()->create();
        $media = Media::query()->create([
            'path' => 'media/catalog-technician.jpg',
            'original_name' => 'catalog-technician.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 2048,
            'width' => 1280,
            'height' => 720,
            'alt' => 'Technician preparing for a service visit',
        ]);
        $image = [
            'media_id' => $media->id,
            'alt' => 'Context-specific technician image',
        ];
        $payload = [
            'title' => 'Catalog media page',
            'blocks' => [
                [
                    'type' => 'hero/split',
                    'props' => [
                        'heading' => 'Catalog media',
                        'image' => $image,
                        'badge' => "Locally\nOwned",
                    ],
                ],
                [
                    'type' => 'team/grid',
                    'props' => [
                        'heading' => 'Service team',
                        'members' => [[
                            'name' => 'Verified Team Member',
                            'role' => 'Technician',
                            'image' => $image,
                        ]],
                    ],
                ],
                [
                    'type' => 'cta/image-split',
                    'props' => [
                        'heading' => 'Request service',
                        'cta' => ['label' => 'See services', 'href' => '/services'],
                        'image' => $image,
                    ],
                ],
                [
                    'type' => 'content/story-checklist',
                    'props' => [
                        'heading' => 'What to expect',
                        'items' => ['Clear communication'],
                        'image' => $image,
                        'inset' => $image,
                    ],
                ],
                [
                    'type' => 'testimonials/carousel',
                    'props' => [
                        'heading' => 'Customer stories',
                        'items' => [[
                            'type' => 'video',
                            'title' => 'Verified customer video',
                            'video_url' => 'https://video.example/customer',
                            'transcript' => 'Verified transcript of the approved customer video.',
                            'poster' => $image,
                        ]],
                    ],
                ],
            ],
        ];
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft(
            $this->identity('catalog-media'),
            $payload,
            $user,
        );
        $lifecycle->publish($entry, $entry->revisions()->sole(), 0, $user);

        $response = $this->get('/catalog-media')->assertOk();
        $mediaPath = route('media.public', ['media' => $media], false);

        $response
            ->assertSee($mediaPath, false)
            ->assertSee('Context-specific technician image')
            ->assertSee('Verified Team Member')
            ->assertSee('Verified customer video')
            ->assertSee("Locally\nOwned");
        $this->assertGreaterThanOrEqual(6, substr_count($response->getContent(), $mediaPath));
    }

    /**
     * @return array<string, ContentKind|PageTemplate|string>
     */
    private function identity(string $slug): array
    {
        return [
            'kind' => ContentKind::Page,
            'slug' => $slug,
            'title' => 'Managed page',
            'template' => PageTemplate::Standard,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $heading): array
    {
        return [
            'title' => $heading,
            'description' => 'Managed description',
            'blocks' => [[
                'type' => 'hero/split',
                'props' => ['heading' => $heading],
            ]],
        ];
    }

    /**
     * The homepage headline, read from the page file rather than hardcoded.
     *
     * These tests are about publication precedence and page rendering, not
     * about the wording. Pinning the copy made every brand-voice edit break
     * tests that have nothing to do with copy.
     */
    private function homepageHeadline(): string
    {
        $page = json_decode(
            (string) file_get_contents(base_path('content/pages/home.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        return $page['blocks'][0]['props']['heading'];
    }

    private function homepageTitle(): string
    {
        $page = json_decode(
            (string) file_get_contents(base_path('content/pages/home.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        return $page['title'];
    }
}
