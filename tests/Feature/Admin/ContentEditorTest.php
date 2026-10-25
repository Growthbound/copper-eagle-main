<?php

namespace Tests\Feature\Admin;

use App\Content\ContentDraftScaffold;
use App\Content\ContentLifecycle;
use App\Content\Enums\ContentKind;
use App\Content\Enums\ContentSource;
use App\Content\Enums\ContentStatus;
use App\Content\Enums\PageTemplate;
use App\Enums\MediaKind;
use App\Livewire\Admin\ContentEditor;
use App\Models\ContentEntry;
use App\Models\Media;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\WritesRecordFixtures;
use Tests\TestCase;

class ContentEditorTest extends TestCase
{
    use RefreshDatabase;
    use WritesRecordFixtures;

    protected function tearDown(): void
    {
        $this->tearDownRecordFixtures();

        parent::tearDown();
    }

    public function test_owner_saves_structured_content_and_identity_as_a_new_revision(): void
    {
        [$owner, $entry] = $this->entry();
        $this->actingAs($owner);

        Livewire::test(ContentEditor::class, ['entry' => $entry])
            ->set('title', 'Updated owner guide')
            ->set('slug', 'guides/updated-owner-guide')
            ->set('payload.description', 'Updated structured description.')
            ->call('addBlock', 'content/rich-text')
            ->set('payload.blocks.1.props.heading', 'Helpful details')
            ->set('payload.blocks.1.props.nodes', [[
                'type' => 'paragraph',
                'segments' => [['text' => 'A structured paragraph.']],
            ]])
            ->call('save')
            ->assertHasNoErrors();

        $entry->refresh();
        $revision = $entry->revisions()->latest('revision_number')->firstOrFail();
        $this->assertSame('Updated owner guide', $entry->title);
        $this->assertSame('guides/updated-owner-guide', $entry->slug);
        $this->assertSame(2, $revision->revision_number);
        $this->assertSame('content/rich-text', $revision->payload['blocks'][1]['type']);
        $this->assertSame(1, $entry->lock_version);
    }

    public function test_stale_editor_gets_a_visible_conflict_without_overwriting(): void
    {
        [$owner, $entry] = $this->entry();
        $this->actingAs($owner);
        $first = Livewire::test(ContentEditor::class, ['entry' => $entry]);
        $stale = Livewire::test(ContentEditor::class, ['entry' => $entry]);

        $first
            ->set('payload.description', 'First save wins.')
            ->call('save')
            ->assertHasNoErrors();
        $stale
            ->set('payload.description', 'Stale overwrite.')
            ->call('save')
            ->assertHasErrors('conflict');

        $this->assertSame(2, $entry->revisions()->count());
        $this->assertSame(
            'First save wins.',
            $entry->revisions()->latest('revision_number')->firstOrFail()->payload['description'],
        );
    }

    public function test_published_entry_editor_preserves_latest_draft_identity_across_saves(): void
    {
        [$owner, $entry] = $this->entry();
        $lifecycle = app(ContentLifecycle::class);
        $published = $lifecycle->publish($entry, $entry->revisions()->sole(), 0, $owner);
        $draft = $lifecycle->saveRevision(
            $published,
            $published->publishedRevision->payload,
            1,
            $owner,
            ContentSource::Admin,
            identity: [
                'kind' => ContentKind::Page,
                'slug' => 'guides/renamed-draft',
                'title' => 'Renamed draft',
                'template' => PageTemplate::Standard,
            ],
        );
        $this->actingAs($owner);

        Livewire::test(ContentEditor::class, ['entry' => $entry->fresh()])
            ->assertSet('title', 'Renamed draft')
            ->assertSet('slug', 'guides/renamed-draft')
            ->set('payload.description', 'Second draft save.')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('title', 'Renamed draft')
            ->assertSet('slug', 'guides/renamed-draft');

        $this->assertSame(
            'guides/renamed-draft',
            $draft->entry->revisions()->latest('revision_number')->firstOrFail()->identity['slug'],
        );
    }

    public function test_schedule_uses_the_configured_site_timezone(): void
    {
        config()->set('app.timezone', 'America/Chicago');
        [$owner, $entry] = $this->entry();
        $this->actingAs($owner);
        $local = CarbonImmutable::now('America/Chicago')->addDay()->format('Y-m-d\TH:i');
        $expectedUtc = CarbonImmutable::createFromFormat(
            'Y-m-d\TH:i',
            $local,
            'America/Chicago',
        )->utc();

        Livewire::test(ContentEditor::class, ['entry' => $entry])
            ->set('scheduledFor', $local)
            ->call('schedule')
            ->assertHasNoErrors()
            ->assertSet('scheduledFor', $local);

        $this->assertTrue($entry->fresh()->scheduled_for->equalTo($expectedUtc));
    }

    public function test_owner_can_validate_preview_publish_schedule_and_archive_from_editor(): void
    {
        [$owner, $entry] = $this->entry();
        $this->actingAs($owner);

        $component = Livewire::test(ContentEditor::class, ['entry' => $entry])
            ->call('validateContent')
            ->assertHasNoErrors()
            ->call('preview')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('content_preview_tokens', 1);

        $component->call('publish')->assertHasNoErrors();
        $this->assertSame(ContentStatus::Published, $entry->fresh()->status);

        $component
            ->set('scheduledFor', CarbonImmutable::now()->addDay()->format('Y-m-d\TH:i'))
            ->call('schedule')
            ->assertHasNoErrors();
        $this->assertSame(ContentStatus::Scheduled, $entry->fresh()->status);

        $component->call('cancelSchedule')->assertHasNoErrors();
        $this->assertSame(ContentStatus::Published, $entry->fresh()->status);

        $component->call('archive')->assertHasNoErrors();
        $this->assertSame(ContentStatus::Archived, $entry->fresh()->status);
    }

    public function test_editor_validation_reports_lifecycle_identity_conflicts(): void
    {
        [$owner, $entry] = $this->entry();
        $lifecycle = app(ContentLifecycle::class);
        $reserved = $lifecycle->createDraft(
            [
                'kind' => ContentKind::Page,
                'slug' => 'guides/reservation-owner',
                'title' => 'Reservation owner',
                'template' => PageTemplate::Standard,
            ],
            [
                'title' => 'Reservation owner',
                'blocks' => [[
                    'type' => 'hero/interior',
                    'props' => ['heading' => 'Reservation owner'],
                ]],
            ],
            $owner,
        );
        $reserved = $lifecycle->publish(
            $reserved,
            $reserved->revisions()->sole(),
            0,
            $owner,
        );
        $scheduled = $lifecycle->saveRevision(
            $reserved,
            [
                'title' => 'Scheduled owner',
                'blocks' => [[
                    'type' => 'hero/interior',
                    'props' => ['heading' => 'Scheduled owner'],
                ]],
            ],
            1,
            $owner,
            ContentSource::Admin,
            identity: [
                'kind' => ContentKind::Page,
                'slug' => 'guides/reserved-path',
                'title' => 'Scheduled owner',
                'template' => PageTemplate::Standard,
            ],
        );
        $lifecycle->schedule(
            $reserved->fresh(),
            $scheduled,
            CarbonImmutable::now()->addDay(),
            2,
            $owner,
        );
        $this->actingAs($owner);

        $component = Livewire::test(ContentEditor::class, ['entry' => $entry])
            ->set('slug', 'guides/reserved-path')
            ->call('validateContent')
            ->assertHasErrors('payload');

        $this->assertArrayHasKey(
            '/identity/slug',
            $component->get('validationMessages'),
        );
    }

    public function test_owner_can_insert_existing_library_media_into_a_structured_field(): void
    {
        [$owner, $entry] = $this->entry();
        $this->actingAs($owner);
        $media = Media::query()->create([
            'path' => 'media/example.jpg',
            'original_name' => 'example.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'width' => 1200,
            'height' => 800,
            'alt' => 'Documented project photo',
        ]);
        $path = 'blocks.1.props.images';
        $key = hash('sha256', $path);

        Livewire::test(ContentEditor::class, ['entry' => $entry])
            ->call('addBlock', 'photo-grid/gallery')
            ->set('payload.blocks.1.props.heading', 'Documented project')
            ->set('selectedMedia.'.$key, $media->id)
            ->call('insertMedia', $key)
            ->assertSet('payload.blocks.1.props.images.0.media_id', $media->id)
            ->assertSet('payload.blocks.1.props.images.0.alt', 'Documented project photo');
    }

    public function test_optional_structured_fields_are_registered_before_they_have_values(): void
    {
        [$owner, $entry] = $this->entry();
        $this->actingAs($owner);
        $path = 'blocks.1.props.secondaryAction';
        $key = hash('sha256', $path);

        Livewire::test(ContentEditor::class, ['entry' => $entry])
            ->call('addBlock', 'hero/service')
            ->set('payload.blocks.1.props.heading', 'Service')
            ->set('payload.blocks.1.props.primaryAction', [
                'label' => 'Book',
                'href' => '#quote',
            ])
            ->set('jsonFields.'.$key, json_encode([
                'label' => 'Learn more',
                'href' => '/about',
            ]))
            ->call('applyJsonField', $key)
            ->assertHasNoErrors('jsonFields.'.$key)
            ->assertSet('payload.blocks.1.props.secondaryAction.href', '/about');
    }

    public function test_schema_scalar_fields_render_type_aware_controls(): void
    {
        [$owner, $entry] = $this->entry();
        $payload = $entry->latestRevision->payload;
        $payload['blocks'][] = [
            'type' => 'cta/quote-form-split',
            'props' => [
                'heading' => 'Request a quote',
                'showMessage' => false,
            ],
        ];
        $payload['blocks'][] = [
            'type' => 'faq/highlighted',
            'props' => [
                'heading' => 'Questions',
                'items' => [[
                    'question' => 'What happens next?',
                    'answer' => 'We will explain the process.',
                ]],
                'openIndex' => 0,
            ],
        ];
        app(ContentLifecycle::class)->saveRevision(
            $entry,
            $payload,
            0,
            $owner,
            ContentSource::Admin,
        );
        $this->actingAs($owner);

        Livewire::test(ContentEditor::class, ['entry' => $entry->fresh()])
            ->assertSeeHtml('type="checkbox"')
            ->assertSeeHtml('wire:model="payload.blocks.1.props.showMessage"')
            ->assertSeeHtml('type="number"')
            ->assertSeeHtml('wire:model.number.blur="payload.blocks.2.props.openIndex"');
    }

    public function test_media_insertion_does_not_invent_alt_text_or_evidence_sources(): void
    {
        [$owner, $entry] = $this->entry();
        $this->actingAs($owner);
        $media = Media::query()->create([
            'path' => 'media/IMG_4821.jpg',
            'original_name' => 'IMG_4821.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'width' => 1200,
            'height' => 800,
            'alt' => null,
            'title' => null,
            'source_url' => null,
        ]);
        $path = 'blocks.1.props.images';
        $key = hash('sha256', $path);
        $component = Livewire::test(ContentEditor::class, ['entry' => $entry])
            ->call('addBlock', 'photo-grid/gallery')
            ->set('payload.blocks.1.props.heading', 'Documented project')
            ->set('selectedMedia.'.$key, $media->id)
            ->call('insertMedia', $key);
        $reference = $component->get('payload')['blocks'][1]['props']['images'][0];

        $this->assertArrayNotHasKey('alt', $reference);
        $this->assertArrayNotHasKey('source_label', $reference);
        $this->assertArrayNotHasKey('source_url', $reference);
        $this->assertSame(1200, $reference['width']);
        $this->assertSame(800, $reference['height']);
    }

    public function test_video_insertion_emits_only_the_renderer_video_contract(): void
    {
        [$owner, $entry] = $this->entry();
        $this->actingAs($owner);
        $video = Media::query()->create([
            'path' => 'media/customer-story.mp4',
            'original_name' => 'customer-story.mp4',
            'mime_type' => 'video/mp4',
            'size' => 1024,
            'kind' => MediaKind::Video,
            'alt' => null,
            'width' => null,
            'height' => null,
            'title' => 'Customer story',
            'source_url' => 'https://example.com/customer-story',
        ]);
        $poster = Media::query()->create([
            'path' => 'media/customer-story-poster.jpg',
            'original_name' => 'customer-story-poster.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 512,
            'kind' => MediaKind::Image,
            'alt' => 'Customer speaking about their service experience',
            'width' => 1280,
            'height' => 720,
        ]);
        $path = 'blocks.1.props.video';
        $key = hash('sha256', $path);
        $posterPath = 'blocks.1.props.poster';
        $posterKey = hash('sha256', $posterPath);
        $component = Livewire::test(ContentEditor::class, ['entry' => $entry])
            ->call('addBlock', 'media/video')
            ->set('payload.blocks.1.props.heading', 'Customer story')
            ->set('payload.blocks.1.props.transcript', 'A verified customer story.')
            ->set('selectedMedia.'.$key, $video->id)
            ->call('insertMedia', $key)
            ->assertHasNoErrors('selectedMedia.'.$key)
            ->set('selectedMedia.'.$posterKey, $poster->id)
            ->call('insertMedia', $posterKey)
            ->assertHasNoErrors('selectedMedia.'.$posterKey);

        $this->assertSame([
            'src' => $video->url(),
            'type' => 'video/mp4',
        ], $component->get('payload')['blocks'][1]['props']['video']);

        $component->call('save')->assertHasNoErrors();
    }

    public function test_owner_can_explicitly_mark_a_fixed_template_scaffold_as_replaced(): void
    {
        // The pillar's service-areas section needs at least one real area.
        $this->fixtureArea();
        $owner = User::factory()->create();
        $template = PageTemplate::ServicePillar;
        $entry = app(ContentLifecycle::class)->createDraft(
            [
                'kind' => ContentKind::Page,
                'slug' => 'draft-service',
                'title' => 'Draft service',
                'template' => $template,
            ],
            app(ContentDraftScaffold::class)->for($template, 'Draft service'),
            $owner,
        );
        $this->actingAs($owner);

        Livewire::test(ContentEditor::class, ['entry' => $entry])
            ->assertSet('payload._scaffold', true)
            ->call('markScaffoldReplaced')
            ->assertSet('payload._scaffold', null)
            ->assertSee('Save a new revision before validation or publication.');
    }

    /** @return array{User, ContentEntry} */
    private function entry(): array
    {
        $owner = User::factory()->create();
        $entry = app(ContentLifecycle::class)->createDraft(
            [
                'kind' => ContentKind::Page,
                'slug' => 'guides/owner-guide',
                'title' => 'Owner guide',
                'template' => PageTemplate::Standard,
            ],
            [
                'title' => 'Owner guide',
                'description' => 'Original description.',
                'blocks' => [[
                    'type' => 'hero/interior',
                    'props' => ['heading' => 'Owner guide'],
                ]],
            ],
            $owner,
        );

        return [$owner, $entry];
    }
}
