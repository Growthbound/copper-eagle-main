<?php

namespace Tests\Feature;

use App\Content\ContentLifecycle;
use App\Content\Enums\ContentKind;
use App\Content\Enums\ContentSource;
use App\Content\Enums\ContentStatus;
use App\Content\Enums\PageTemplate;
use App\Content\Exceptions\ContentConflict;
use App\Content\Exceptions\ContentValidationFailed;
use App\Models\ContentRevision;
use App\Models\Media;
use App\Models\User;
use App\Support\Integrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ManagedContentFixtures;
use Tests\TestCase;

class ContentPublicationTest extends TestCase
{
    use ManagedContentFixtures;
    use RefreshDatabase;

    public function test_draft_keeps_json_homepage_until_exact_revision_is_published(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft(
            $this->identity('/'),
            $this->payload('Database-managed homepage'),
            $user,
        );
        $revision = $entry->revisions()->sole();

        $this->get('/')
            ->assertOk()
            ->assertSee($this->homepageHeadlineMarkup(), false)
            ->assertDontSee('Database-managed homepage');

        $published = $lifecycle->publish($entry, $revision, 0, $user);

        $this->assertSame(ContentStatus::Published, $published->status);
        $this->assertSame($revision->id, $published->published_revision_id);
        $this->get('/')
            ->assertOk()
            ->assertSee('Database-managed homepage')
            ->assertDontSee($this->homepageHeadlineMarkup(), false);
    }

    public function test_preview_token_is_hashed_revision_bound_noindex_and_revocable(): void
    {
        Integrations::save([
            'gtm_container_id' => 'GTM-PREVIEW1',
            'head_snippet' => '<script data-private-preview-tracker></script>',
            'body_snippet' => '<script data-private-preview-widget></script>',
        ]);
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft(
            $this->identity('preview-example'),
            $this->payload('Private preview heading'),
            $user,
        );
        $revision = $entry->revisions()->sole();

        $issued = $lifecycle->issuePreview($entry, $revision, 0, $user);

        $this->assertNotSame($issued->token, $issued->preview->token_hash);
        $this->assertSame(hash('sha256', $issued->token), $issued->preview->token_hash);
        $this->assertArrayNotHasKey('token_hash', $issued->preview->toArray());
        $previewResponse = $this->get($issued->url())
            ->assertOk()
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('Private preview heading')
            ->assertSee('name="robots" content="noindex, nofollow"', false)
            ->assertDontSee('rel="canonical"', false)
            ->assertDontSee('GTM-PREVIEW1', false)
            ->assertDontSee('data-private-preview-tracker', false)
            ->assertDontSee('data-private-preview-widget', false);
        $cacheControl = (string) $previewResponse->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);

        $issued->preview->update(['revoked_at' => now()]);

        $this->get($issued->url())->assertNotFound();
    }

    public function test_navigation_and_footer_previews_render_candidate_site_structure(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $navigation = $lifecycle->createDraft([
            'kind' => ContentKind::Navigation,
            'slug' => 'primary',
            'title' => 'Candidate navigation',
            'template' => PageTemplate::Standard,
        ], [
            'title' => 'Candidate navigation',
            'blocks' => [],
            'items' => [
                ['label' => 'Candidate services link', 'href' => '/services'],
            ],
        ], $user);
        $navigationPreview = $lifecycle->issuePreview(
            $navigation,
            $navigation->revisions()->sole(),
            0,
            $user,
        );

        $this->get($navigationPreview->url())
            ->assertOk()
            ->assertSee('Candidate services link')
            ->assertSee('candidate navigation revision in its public site context');

        $footer = $lifecycle->createDraft([
            'kind' => ContentKind::Footer,
            'slug' => 'site-footer',
            'title' => 'Candidate footer',
            'template' => PageTemplate::Standard,
        ], [
            'title' => 'Candidate footer',
            'blocks' => [],
            'company' => [
                ['label' => 'Candidate company link', 'href' => '/services'],
            ],
            'resources' => [
                ['label' => 'Candidate resource link', 'href' => '/sitemap.xml'],
            ],
        ], $user);
        $footerPreview = $lifecycle->issuePreview(
            $footer,
            $footer->revisions()->sole(),
            0,
            $user,
        );

        $this->get($footerPreview->url())
            ->assertOk()
            ->assertSee('Candidate company link')
            ->assertSee('Candidate resource link')
            ->assertSee('candidate footer revision is rendered at the bottom');
    }

    public function test_article_preview_renders_the_candidate_article_template(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $title = 'Candidate article preview';
        $entry = $lifecycle->createDraft([
            'kind' => ContentKind::Article,
            'slug' => 'blog/candidate-article-preview',
            'title' => $title,
            'template' => PageTemplate::DecisionGuide,
        ], $this->fixedPayload(PageTemplate::DecisionGuide, $title), $user);
        $preview = $lifecycle->issuePreview(
            $entry,
            $entry->revisions()->sole(),
            0,
            $user,
        );

        $this->get($preview->url())
            ->assertOk()
            ->assertSee($title)
            ->assertSee('Fictional educational summary.')
            ->assertSee('On this fictional page');
    }

    public function test_preview_uses_the_candidate_revision_kind_when_it_differs_from_the_published_entry(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft(
            $this->identity('kind-transition'),
            $this->payload('Published page'),
            $user,
        );
        $entry = $lifecycle->publish(
            $entry,
            $entry->revisions()->sole(),
            0,
            $user,
        );
        $navigation = $lifecycle->saveRevision(
            $entry,
            [
                'title' => 'Candidate navigation',
                'blocks' => [],
                'items' => [
                    ['label' => 'Revision-kind navigation link', 'href' => '/services'],
                ],
            ],
            1,
            $user,
            ContentSource::Admin,
            identity: [
                'kind' => ContentKind::Navigation,
                'slug' => 'primary',
                'title' => 'Candidate navigation',
                'template' => PageTemplate::Standard,
            ],
        );

        $this->assertSame(ContentKind::Page, $entry->fresh()->kind);
        $this->assertSame('navigation', $navigation->identity['kind']);

        $preview = $lifecycle->issuePreview($entry, $navigation, 2, $user);

        $this->get($preview->url())
            ->assertOk()
            ->assertSee('Revision-kind navigation link')
            ->assertSee('candidate navigation revision in its public site context')
            ->assertDontSee('Published page');
    }

    public function test_expired_preview_token_is_not_accessible(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft(
            $this->identity('expired-preview'),
            $this->payload('Expired preview heading'),
            $user,
        );
        $issued = $lifecycle->issuePreview(
            $entry,
            $entry->revisions()->sole(),
            0,
            $user,
        );
        $issued->preview->update(['expires_at' => now()->subSecond()]);

        $this->get($issued->url())->assertNotFound();
    }

    public function test_managed_content_preview_renders_lead_forms_in_non_persistent_mode(): void
    {
        $user = User::factory()->create();
        $payload = $this->payload('Preview form');
        $payload['blocks'][] = [
            'type' => 'cta/quote-form',
            'props' => ['heading' => 'Request a visit'],
        ];
        $entry = app(ContentLifecycle::class)->createDraft(
            $this->identity('preview-form'),
            $payload,
            $user,
        );
        $issued = app(ContentLifecycle::class)->issuePreview(
            $entry,
            $entry->revisions()->sole(),
            0,
            $user,
        );

        $this->get($issued->url())
            ->assertOk()
            ->assertSee('data-preview="true"', false);
    }

    public function test_draft_identity_changes_do_not_move_the_live_page_until_publication(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft(
            $this->identity('original-page'),
            $this->payload('Original public page'),
            $user,
        );
        $original = $entry->revisions()->sole();
        $entry = $lifecycle->publish($entry, $original, 0, $user);
        $renamed = $lifecycle->saveRevision(
            $entry,
            $this->payload('Renamed draft page'),
            1,
            $user,
            ContentSource::Admin,
            identity: $this->identity('renamed-page'),
        );

        $entry->refresh();
        $this->assertSame('original-page', $entry->slug);
        $this->assertSame('renamed-page', $renamed->identity['slug']);
        $this->get('/original-page')->assertOk()->assertSee('Original public page');
        $this->get('/renamed-page')->assertNotFound();

        $preview = $lifecycle->issuePreview($entry, $renamed, 2, $user);
        $this->get($preview->url())->assertOk()->assertSee('Renamed draft page');

        $lifecycle->publish($entry, $renamed, 2, $user);

        $this->get('/original-page')->assertNotFound();
        $this->get('/renamed-page')->assertOk()->assertSee('Renamed draft page');
    }

    public function test_preview_lock_check_and_token_issuance_share_one_transaction_boundary(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft(
            $this->identity('atomic-preview'),
            $this->payload('Atomic preview'),
            $user,
        );
        $revision = $entry->revisions()->sole();
        $lifecycle->saveRevision(
            $entry,
            $this->payload('Newer draft'),
            0,
            $user,
            ContentSource::Admin,
        );

        try {
            $lifecycle->issuePreview($entry, $revision, 0, $user);
            $this->fail('A stale preview request must fail before a token is issued.');
        } catch (ContentConflict $conflict) {
            $this->assertSame(0, $conflict->expectedLockVersion);
            $this->assertSame(1, $conflict->currentLockVersion);
        }

        $this->assertDatabaseCount('content_preview_tokens', 0);
    }

    public function test_failed_preview_validation_is_committed_to_the_audit_log(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $revision = $lifecycle->importDraft(
            $this->identity('invalid-preview'),
            [
                'title' => 'Invalid preview',
                'blocks' => [],
                'raw_html' => '<script>alert(1)</script>',
            ],
            $user,
            'invalid-preview-fixture',
        );
        $entry = $revision->entry()->firstOrFail();

        try {
            $lifecycle->issuePreview($entry, $revision, 0, $user);
            $this->fail('Invalid content must not receive a preview token.');
        } catch (ContentValidationFailed) {
            $this->assertDatabaseHas('content_audit_events', [
                'content_entry_id' => $entry->id,
                'content_revision_id' => $revision->id,
                'action' => 'validation_failed',
            ]);
        }

        $this->assertDatabaseCount('content_preview_tokens', 0);
    }

    public function test_import_keeps_stale_media_references_repairable_without_foreign_key_failure(): void
    {
        $user = User::factory()->create();
        $revision = app(ContentLifecycle::class)->importDraft(
            $this->identity('stale-import-media'),
            [
                'title' => 'Repairable imported page',
                'blocks' => [[
                    'type' => 'content/split',
                    'props' => [
                        'heading' => 'Imported story',
                        'body' => 'Replace the missing image before publication.',
                        'image' => ['media_id' => 999_999],
                    ],
                ]],
            ],
            $user,
            'legacy:stale-import-media',
        );

        $this->assertDatabaseHas('content_revisions', ['id' => $revision->id]);
        $this->assertDatabaseCount('media_usages', 0);
    }

    public function test_imported_catalog_media_url_must_be_repaired_before_publication(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $revision = $lifecycle->importDraft(
            $this->identity('stale-import-media-url'),
            [
                'title' => 'Repairable imported gallery',
                'blocks' => [[
                    'type' => 'photo-grid/gallery',
                    'props' => [
                        'heading' => 'Imported project',
                        'images' => [[
                            'src' => '/media/999999',
                            'alt' => 'Imported project',
                            'width' => 1200,
                            'height' => 800,
                        ]],
                    ],
                ]],
            ],
            $user,
            'legacy:stale-import-media-url',
        );
        $entry = $revision->entry()->firstOrFail();

        try {
            $lifecycle->publish($entry, $revision, 0, $user);
            $this->fail('A stale catalog media URL must not be published.');
        } catch (ContentValidationFailed $failure) {
            $this->assertArrayHasKey(
                '/blocks/0/props/images/0/src',
                $failure->result->errors(),
            );
        }

        $this->assertNull($entry->fresh()->published_revision_id);
        $this->assertDatabaseCount('media_usages', 0);
    }

    public function test_repaired_imported_media_is_reindexed_before_preview_and_publication(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $previewRevision = $lifecycle->importDraft(
            $this->identity('repaired-preview-media'),
            [
                'title' => 'Repaired preview media',
                'blocks' => [[
                    'type' => 'content/split',
                    'props' => [
                        'heading' => 'Imported preview story',
                        'body' => 'The catalog record arrived after the immutable revision.',
                        'image' => ['media_id' => 999_998],
                    ],
                ]],
            ],
            $user,
            'legacy:repaired-preview-media',
        );
        $publishRevision = $lifecycle->importDraft(
            $this->identity('repaired-publish-media'),
            [
                'title' => 'Repaired publish media',
                'blocks' => [[
                    'type' => 'content/split',
                    'props' => [
                        'heading' => 'Imported publication story',
                        'body' => 'The catalog record arrived after the immutable revision.',
                        'image' => ['media_id' => 999_999],
                    ],
                ]],
            ],
            $user,
            'legacy:repaired-publish-media',
        );

        foreach ([999_998, 999_999] as $mediaId) {
            Media::unguarded(fn () => Media::create([
                'id' => $mediaId,
                'path' => "media/repaired-{$mediaId}.jpg",
                'original_name' => "repaired-{$mediaId}.jpg",
                'mime_type' => 'image/jpeg',
                'size' => 1024,
                'width' => 1200,
                'height' => 800,
                'alt' => 'A repaired imported media record',
            ]));
        }

        $lifecycle->issuePreview(
            $previewRevision->entry()->firstOrFail(),
            $previewRevision,
            0,
            $user,
        );
        $lifecycle->publish(
            $publishRevision->entry()->firstOrFail(),
            $publishRevision,
            0,
            $user,
        );

        $this->assertDatabaseHas('media_usages', [
            'media_id' => 999_998,
            'content_revision_id' => $previewRevision->id,
            'json_pointer' => '/blocks/0/props/image/media_id',
        ]);
        $this->assertDatabaseHas('media_usages', [
            'media_id' => 999_999,
            'content_revision_id' => $publishRevision->id,
            'json_pointer' => '/blocks/0/props/image/media_id',
        ]);
    }

    public function test_standard_page_without_a_hero_receives_one_page_level_heading(): void
    {
        $user = User::factory()->create();
        $entry = app(ContentLifecycle::class)->createDraft(
            $this->identity('answer-only'),
            [
                'title' => 'Answer-only page',
                'blocks' => [[
                    'type' => 'content/direct-answer',
                    'props' => [
                        'heading' => 'A direct answer',
                        'answer' => [['text' => 'Useful guidance.']],
                    ],
                ]],
            ],
            $user,
        );

        app(ContentLifecycle::class)->publish(
            $entry,
            $entry->revisions()->sole(),
            0,
            $user,
        );

        $html = $this->get('/answer-only')->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('/<h1\b/i', $html));
        $this->assertStringContainsString('<header class="site-header edge-panel">', $html);
        $this->assertStringContainsString('aria-label="Primary navigation"', $html);
        $this->assertStringContainsString('<h1>Answer-only page</h1>', $html);
    }

    public function test_failed_publication_keeps_the_current_revision_live_and_records_failure(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft(
            $this->identity('/'),
            $this->payload('Published homepage'),
            $user,
        );
        $publishedRevision = $entry->revisions()->sole();
        $entry = $lifecycle->publish($entry, $publishedRevision, 0, $user);
        $invalid = ContentRevision::create([
            'content_entry_id' => $entry->id,
            'revision_number' => 2,
            'schema_version' => 1,
            'payload' => [
                ...$this->payload('Unsafe homepage'),
                'raw_html' => '<script>alert(1)</script>',
            ],
            'source' => ContentSource::Admin,
            'created_by' => $user->id,
        ]);

        try {
            $lifecycle->publish($entry, $invalid, 1, $user);
            $this->fail('An invalid revision must not replace public content.');
        } catch (ContentValidationFailed $failure) {
            $this->assertArrayHasKey('/raw_html', $failure->result->errors());
        }

        $entry->refresh();
        $this->assertSame($publishedRevision->id, $entry->published_revision_id);
        $this->assertSame(1, $entry->lock_version);
        $this->get('/')->assertSee('Published homepage')->assertDontSee('Unsafe homepage');
        $this->assertDatabaseHas('content_audit_events', [
            'content_entry_id' => $entry->id,
            'action' => 'validation_failed',
        ]);
    }

    public function test_rollback_creates_new_history_then_publishes_that_snapshot(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft(
            $this->identity('/'),
            $this->payload('Original homepage'),
            $user,
        );
        $original = $entry->revisions()->sole();
        $entry = $lifecycle->publish($entry, $original, 0, $user);
        $replacement = $lifecycle->saveRevision(
            $entry,
            $this->payload('Replacement homepage'),
            1,
            $user,
            ContentSource::Admin,
        );
        $entry = $lifecycle->publish($entry->fresh(), $replacement, 2, $user);

        $rollback = $lifecycle->rollback(
            $entry,
            $original,
            3,
            $user,
            ContentSource::Admin,
        );

        $this->assertSame(3, $rollback->revision_number);
        $this->assertNotSame($original->id, $rollback->id);
        $this->assertSame($original->payload, $rollback->payload);
        $this->assertSame($rollback->id, $entry->fresh()->published_revision_id);
        $this->assertSame(4, $entry->fresh()->lock_version);
        $this->get('/')->assertSee('Original homepage')->assertDontSee('Replacement homepage');
        $this->assertDatabaseHas('content_audit_events', [
            'content_entry_id' => $entry->id,
            'content_revision_id' => $rollback->id,
            'action' => 'rollback',
        ]);
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
            'description' => 'A managed page description.',
            'blocks' => [[
                'type' => 'hero/split',
                'props' => [
                    'eyebrow' => 'Example Co.',
                    'heading' => $heading,
                    'body' => 'Helpful, professional guidance.',
                ],
            ]],
        ];
    }

    /**
     * The homepage headline, read from the page file rather than hardcoded.
     *
     * These tests are about publication precedence and page rendering, not
     * about the wording. Pinning the copy made every brand-voice edit break
     * tests that have nothing to do with copy.
     *
     * Matched as the hero's heading element rather than bare text: the
     * placeholder headline can be the site name, which the header and footer
     * show on every page whichever version of the homepage is live.
     */
    private function homepageHeadlineMarkup(): string
    {
        $page = json_decode(
            (string) file_get_contents(base_path('content/pages/home.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        return '<h1 id="hero-title">'.e($page['blocks'][0]['props']['heading']).'</h1>';
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
