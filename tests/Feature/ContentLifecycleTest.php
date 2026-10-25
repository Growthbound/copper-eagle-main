<?php

namespace Tests\Feature;

use App\Content\ContentLifecycle;
use App\Content\Enums\ContentKind;
use App\Content\Enums\ContentSource;
use App\Content\Enums\ContentStatus;
use App\Content\Enums\PageTemplate;
use App\Content\Exceptions\ContentConflict;
use App\Content\Exceptions\ContentValidationFailed;
use App\Enums\MediaKind;
use App\Models\Media;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ManagedContentFixtures;
use Tests\TestCase;

class ContentLifecycleTest extends TestCase
{
    use ManagedContentFixtures;
    use RefreshDatabase;

    public function test_it_creates_a_draft_then_saves_an_attributed_revision(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft(
            $this->identity(),
            $this->payload('Clearer air starts with a careful inspection.'),
            $user,
        );

        $revision = $lifecycle->saveRevision(
            $entry,
            $this->payload('A careful inspection gives the homeowner a clear starting point.'),
            expectedLockVersion: 0,
            actor: $user,
            source: ContentSource::Admin,
            summary: 'Expanded the opening answer',
        );

        $this->assertSame(2, $revision->revision_number);
        $this->assertSame(1, $entry->fresh()->lock_version);
        $this->assertSame(2, $entry->revisions()->count());
        $this->assertDatabaseHas('content_audit_events', [
            'content_entry_id' => $entry->id,
            'content_revision_id' => $revision->id,
            'user_id' => $user->id,
            'action' => 'save_revision',
        ]);
    }

    public function test_it_rejects_a_stale_write_without_creating_history(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft(
            $this->identity(),
            $this->payload('Initial copy.'),
            $user,
        );
        $lifecycle->saveRevision(
            $entry,
            $this->payload('Newer copy.'),
            0,
            $user,
            ContentSource::Admin,
        );

        try {
            $lifecycle->saveRevision(
                $entry,
                $this->payload('Stale copy.'),
                0,
                $user,
                ContentSource::Admin,
            );
            $this->fail('A stale write must not overwrite a newer revision.');
        } catch (ContentConflict $conflict) {
            $this->assertSame(0, $conflict->expectedLockVersion);
            $this->assertSame(1, $conflict->currentLockVersion);
        }

        $this->assertSame(2, $entry->revisions()->count());
        $this->assertSame(1, $entry->fresh()->lock_version);
    }

    public function test_invalid_managed_markup_is_rejected_and_audited(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft(
            $this->identity(),
            $this->payload('Initial copy.'),
            $user,
        );
        $invalid = $this->payload('Changed copy.');
        $invalid['raw_html'] = '<script>alert(1)</script>';

        try {
            $lifecycle->saveRevision(
                $entry,
                $invalid,
                0,
                $user,
                ContentSource::Admin,
            );
            $this->fail('Managed content must not accept raw HTML fields.');
        } catch (ContentValidationFailed $failure) {
            $this->assertArrayHasKey('/raw_html', $failure->result->errors());
        }

        $this->assertSame(1, $entry->revisions()->count());
        $this->assertSame(0, $entry->fresh()->lock_version);
        $this->assertDatabaseHas('content_audit_events', [
            'content_entry_id' => $entry->id,
            'action' => 'validation_failed',
        ]);
    }

    public function test_revision_creation_indexes_every_media_reference_by_json_pointer(): void
    {
        $user = User::factory()->create();
        $media = $this->media();
        $payload = [
            'title' => 'About',
            'blocks' => [[
                'type' => 'content/split',
                'props' => [
                    'heading' => 'A careful process',
                    'body' => 'See how the work is prepared.',
                    'image' => [
                        'media_id' => $media->id,
                        'alt' => 'Technician preparing an inspection camera',
                        'width' => 1200,
                        'height' => 800,
                    ],
                ],
            ]],
        ];

        $entry = app(ContentLifecycle::class)->createDraft(
            $this->identity(),
            $payload,
            $user,
        );
        $revision = $entry->revisions()->sole();

        $this->assertDatabaseHas('media_usages', [
            'media_id' => $media->id,
            'content_revision_id' => $revision->id,
            'json_pointer' => '/blocks/0/props/image/media_id',
            'usage_type' => 'image',
        ]);
    }

    public function test_catalog_video_urls_are_indexed_as_protected_media_usage(): void
    {
        $user = User::factory()->create();
        $poster = $this->media();
        $video = Media::create([
            'path' => 'media/customer-story.mp4',
            'original_name' => 'customer-story.mp4',
            'mime_type' => 'video/mp4',
            'size' => 4096,
            'kind' => MediaKind::Video,
        ]);
        $payload = [
            'title' => 'Customer stories',
            'blocks' => [
                [
                    'type' => 'media/video',
                    'props' => [
                        'heading' => 'Watch the service story',
                        'video' => [
                            'src' => $video->url(),
                            'type' => 'video/mp4',
                        ],
                        'poster' => ['media_id' => $poster->id],
                        'transcript' => 'A complete customer story transcript.',
                    ],
                ],
                [
                    'type' => 'testimonials/carousel',
                    'props' => [
                        'heading' => 'Customer videos',
                        'items' => [[
                            'type' => 'video',
                            'title' => 'A customer video',
                            'video_url' => $video->url(),
                            'transcript' => 'A complete customer testimonial transcript.',
                            'poster' => ['media_id' => $poster->id],
                        ]],
                    ],
                ],
            ],
        ];

        $entry = app(ContentLifecycle::class)->createDraft(
            $this->identity(),
            $payload,
            $user,
        );
        $revision = $entry->revisions()->sole();

        $this->assertDatabaseHas('media_usages', [
            'media_id' => $video->id,
            'content_revision_id' => $revision->id,
            'json_pointer' => '/blocks/0/props/video/src',
            'usage_type' => 'video',
        ]);
        $this->assertDatabaseHas('media_usages', [
            'media_id' => $video->id,
            'content_revision_id' => $revision->id,
            'json_pointer' => '/blocks/1/props/items/0/video_url',
            'usage_type' => 'video',
        ]);
    }

    public function test_catalog_urls_with_an_explicit_default_port_are_indexed(): void
    {
        config(['app.url' => 'https://example.test']);

        $user = User::factory()->create();
        $poster = $this->media();
        $video = Media::create([
            'path' => 'media/default-port-story.mp4',
            'original_name' => 'default-port-story.mp4',
            'mime_type' => 'video/mp4',
            'size' => 4096,
            'kind' => MediaKind::Video,
        ]);
        $payload = [
            'title' => 'Default port story',
            'blocks' => [[
                'type' => 'media/video',
                'props' => [
                    'heading' => 'Watch the service story',
                    'video' => [
                        'src' => "https://example.test:443/media/{$video->id}",
                        'type' => 'video/mp4',
                    ],
                    'poster' => ['media_id' => $poster->id],
                    'transcript' => 'A complete customer story transcript.',
                ],
            ]],
        ];

        $entry = app(ContentLifecycle::class)->createDraft(
            $this->identity(),
            $payload,
            $user,
        );

        $this->assertDatabaseHas('media_usages', [
            'media_id' => $video->id,
            'content_revision_id' => $entry->revisions()->sole()->id,
            'json_pointer' => '/blocks/0/props/video/src',
            'usage_type' => 'video',
        ]);
    }

    public function test_archive_clears_public_state_and_restore_returns_to_draft(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft(
            $this->identity(),
            $this->payload('Initial copy.'),
            $user,
        );
        $revision = $entry->revisions()->sole();
        $entry->forceFill([
            'status' => ContentStatus::Published,
            'published_revision_id' => $revision->id,
            'published_at' => now(),
        ])->save();

        $archived = $lifecycle->archive($entry, 0, $user);

        $this->assertSame(ContentStatus::Archived, $archived->status);
        $this->assertNull($archived->published_revision_id);
        $this->assertNull($archived->published_at);
        $this->assertSame(1, $archived->lock_version);
        $this->assertSame(1, $archived->revisions()->count());

        $restored = $lifecycle->restore($archived, 1, $user);

        $this->assertSame(ContentStatus::Draft, $restored->status);
        $this->assertNull($restored->published_revision_id);
        $this->assertSame(2, $restored->lock_version);
        $this->assertDatabaseHas('content_audit_events', [
            'content_entry_id' => $entry->id,
            'action' => 'archive',
        ]);
        $this->assertDatabaseHas('content_audit_events', [
            'content_entry_id' => $entry->id,
            'action' => 'restore',
        ]);
    }

    public function test_published_page_cannot_be_archived_while_site_structure_links_to_it(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $page = $lifecycle->createDraft(
            $this->identity(),
            $this->payload('Linked page.'),
            $user,
        );
        $page = $lifecycle->publish($page, $page->revisions()->sole(), 0, $user);
        $navigation = $lifecycle->createDraft([
            'kind' => ContentKind::Navigation,
            'slug' => 'primary',
            'title' => 'Primary navigation',
            'template' => PageTemplate::Standard,
        ], [
            'title' => 'Primary navigation',
            'blocks' => [],
            'items' => [['label' => 'About', 'href' => '/about']],
        ], $user);
        $lifecycle->publish(
            $navigation,
            $navigation->revisions()->sole(),
            0,
            $user,
        );

        try {
            $lifecycle->archive($page, 1, $user);
            $this->fail('A linked public page must not be archived.');
        } catch (ContentValidationFailed $failure) {
            $this->assertArrayHasKey('/status', $failure->result->errors());
        }

        $this->assertSame(ContentStatus::Published, $page->fresh()->status);
        $this->assertNotNull($page->fresh()->published_revision_id);
    }

    public function test_linked_public_page_cannot_be_renamed_by_publish_or_schedule(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $page = $lifecycle->createDraft(
            $this->identity(),
            $this->payload('Linked page.'),
            $user,
        );
        $page = $lifecycle->publish($page, $page->revisions()->sole(), 0, $user);
        $renamed = $lifecycle->saveRevision(
            $page,
            $this->payload('Renamed page.'),
            1,
            $user,
            ContentSource::Admin,
            identity: [
                ...$this->identity(),
                'slug' => 'company',
            ],
        );
        $navigation = $lifecycle->createDraft([
            'kind' => ContentKind::Navigation,
            'slug' => 'primary',
            'title' => 'Primary navigation',
            'template' => PageTemplate::Standard,
        ], [
            'title' => 'Primary navigation',
            'blocks' => [],
            'items' => [['label' => 'About', 'href' => '/about']],
        ], $user);
        $lifecycle->publish($navigation, $navigation->revisions()->sole(), 0, $user);

        foreach ([
            fn () => $lifecycle->publish($page->fresh(), $renamed, 2, $user),
            fn () => $lifecycle->schedule(
                $page->fresh(),
                $renamed,
                CarbonImmutable::now()->addDay(),
                2,
                $user,
            ),
        ] as $transition) {
            try {
                $transition();
                $this->fail('A linked public path must not be stranded by a rename.');
            } catch (ContentValidationFailed $failure) {
                $this->assertArrayHasKey('/identity/slug', $failure->result->errors());
            }
        }

        $this->assertSame('about', $page->fresh()->slug);
        $this->assertSame(ContentStatus::Published, $page->fresh()->status);
    }

    public function test_page_references_block_archive_publish_and_schedule_path_retirement(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $target = $lifecycle->createDraft(
            [...$this->identity(), 'slug' => 'target-page'],
            $this->payload('Target page.'),
            $user,
        );
        $target = $lifecycle->publish(
            $target,
            $target->revisions()->sole(),
            0,
            $user,
        );
        $sourcePayload = $this->payload('Source page.');
        $sourcePayload['blocks'][] = [
            'type' => 'content/related-content',
            'props' => [
                'heading' => 'Related guidance',
                'items' => [[
                    'title' => 'Target page',
                    'href' => '/target-page',
                ]],
            ],
        ];
        $source = $lifecycle->createDraft(
            [...$this->identity(), 'slug' => 'source-page'],
            $sourcePayload,
            $user,
        );
        $lifecycle->publish($source, $source->revisions()->sole(), 0, $user);

        try {
            $lifecycle->archive($target->fresh(), 1, $user);
            $this->fail('A page referenced by published page content must not be archived.');
        } catch (ContentValidationFailed $failure) {
            $this->assertArrayHasKey('/status', $failure->result->errors());
        }

        $renamed = $lifecycle->saveRevision(
            $target->fresh(),
            $this->payload('Renamed target.'),
            1,
            $user,
            ContentSource::Admin,
            identity: [...$this->identity(), 'slug' => 'renamed-target'],
        );

        foreach ([
            fn () => $lifecycle->publish($target->fresh(), $renamed, 2, $user),
            fn () => $lifecycle->schedule(
                $target->fresh(),
                $renamed,
                CarbonImmutable::now()->addDay(),
                2,
                $user,
            ),
        ] as $transition) {
            try {
                $transition();
                $this->fail('A page reference must block retirement through identity transition.');
            } catch (ContentValidationFailed $failure) {
                $this->assertArrayHasKey('/identity/slug', $failure->result->errors());
            }
        }

        $this->assertSame(ContentStatus::Published, $target->fresh()->status);
        $this->assertSame('target-page', $target->fresh()->slug);
        $this->get('/source-page')->assertOk();
        $this->get('/target-page')->assertOk();
    }

    public function test_every_managed_link_field_blocks_retiring_a_published_path(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $cases = ['cta', 'resource-card', 'rich-text', 'breadcrumb'];

        foreach ($cases as $index => $case) {
            $targetSlug = "linked-target-{$index}";
            $sourcePayload = $this->payload("Source page {$index}.");
            $target = $lifecycle->createDraft(
                [...$this->identity(), 'slug' => $targetSlug],
                $this->payload("Linked target {$index}."),
                $user,
            );
            $target = $lifecycle->publish(
                $target,
                $target->revisions()->sole(),
                0,
                $user,
            );

            if ($case === 'cta') {
                $sourcePayload['blocks'][] = [
                    'type' => 'cta/inline-band',
                    'props' => [
                        'heading' => 'Continue',
                        'primaryAction' => [
                            'label' => 'Linked target',
                            'href' => "/{$targetSlug}",
                        ],
                    ],
                ];
            } elseif ($case === 'resource-card') {
                $sourcePayload['blocks'][] = [
                    'type' => 'content/resource-cards',
                    'props' => [
                        'heading' => 'Resources',
                        'items' => [[
                            'title' => 'Linked target',
                            'body' => 'Read the linked guidance.',
                            'href' => "/{$targetSlug}",
                        ]],
                    ],
                ];
            } elseif ($case === 'rich-text') {
                $sourcePayload['blocks'][] = [
                    'type' => 'content/rich-text',
                    'props' => [
                        'heading' => 'Details',
                        'nodes' => [[
                            'type' => 'paragraph',
                            'segments' => [[
                                'text' => 'Read the linked target.',
                                'href' => "/{$targetSlug}",
                            ]],
                        ]],
                    ],
                ];
            } else {
                $sourcePayload['breadcrumbs'] = [
                    ['name' => 'Home', 'url' => '/'],
                    ['name' => 'Linked target', 'url' => "/{$targetSlug}"],
                    ['name' => 'Source', 'url' => '/services'],
                ];
            }

            $source = $lifecycle->createDraft(
                [...$this->identity(), 'slug' => "link-source-{$index}"],
                $sourcePayload,
                $user,
            );
            $lifecycle->publish($source, $source->revisions()->sole(), 0, $user);

            try {
                $lifecycle->archive($target->fresh(), 1, $user);
                $this->fail("The {$case} link must block path retirement.");
            } catch (ContentValidationFailed $failure) {
                $this->assertArrayHasKey('/status', $failure->result->errors());
            }
        }
    }

    public function test_fixed_template_related_content_blocks_archive(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $target = $lifecycle->createDraft(
            [...$this->identity(), 'slug' => 'fixed-target'],
            $this->payload('Fixed target.'),
            $user,
        );
        $target = $lifecycle->publish(
            $target,
            $target->revisions()->sole(),
            0,
            $user,
        );
        $sourcePayload = $this->fixedPayload(PageTemplate::DecisionGuide);
        $sourcePayload['sections']['related-content-next-step']['items'] = [[
            'title' => 'Fixed target',
            'href' => '/fixed-target',
        ]];
        $source = $lifecycle->createDraft([
            ...$this->identity(),
            'slug' => 'fixed-source',
            'template' => PageTemplate::DecisionGuide,
        ], $sourcePayload, $user);
        $lifecycle->publish($source, $source->revisions()->sole(), 0, $user);

        try {
            $lifecycle->archive($target->fresh(), 1, $user);
            $this->fail('A fixed-template page reference must block archive.');
        } catch (ContentValidationFailed $failure) {
            $this->assertArrayHasKey('/status', $failure->result->errors());
        }

        $this->assertSame(ContentStatus::Published, $target->fresh()->status);
    }

    public function test_linked_public_page_cannot_change_kind_without_changing_its_slug(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $page = $lifecycle->createDraft(
            [...$this->identity(), 'slug' => 'primary'],
            $this->payload('Linked page.'),
            $user,
        );
        $page = $lifecycle->publish($page, $page->revisions()->sole(), 0, $user);
        $footer = $lifecycle->createDraft([
            'kind' => ContentKind::Footer,
            'slug' => 'site-footer',
            'title' => 'Footer',
            'template' => PageTemplate::Standard,
        ], [
            'title' => 'Footer',
            'blocks' => [],
            'company' => [['label' => 'Primary page', 'href' => '/primary']],
            'resources' => [],
        ], $user);
        $lifecycle->publish($footer, $footer->revisions()->sole(), 0, $user);
        $navigationRevision = $lifecycle->saveRevision(
            $page,
            [
                'title' => 'Primary navigation',
                'blocks' => [],
                'items' => [['label' => 'Services', 'href' => '/services']],
            ],
            1,
            $user,
            ContentSource::Admin,
            identity: [
                'kind' => ContentKind::Navigation,
                'slug' => 'primary',
                'title' => 'Primary navigation',
                'template' => PageTemplate::Standard,
            ],
        );

        try {
            $lifecycle->publish($page->fresh(), $navigationRevision, 2, $user);
            $this->fail('A linked page path must not disappear through a same-slug kind change.');
        } catch (ContentValidationFailed $failure) {
            $this->assertArrayHasKey('/identity/kind', $failure->result->errors());
        }

        $this->assertSame(ContentKind::Page, $page->fresh()->kind);
        $this->assertSame(ContentStatus::Published, $page->fresh()->status);
    }

    public function test_identity_transition_links_cannot_target_the_page_path_the_transition_retires(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $pageIdentity = [...$this->identity(), 'slug' => 'primary'];
        $page = $lifecycle->createDraft(
            $pageIdentity,
            $this->payload('Current primary page.'),
            $user,
        );
        $page = $lifecycle->publish(
            $page,
            $page->revisions()->sole(),
            0,
            $user,
        );
        $navigationIdentity = [
            'kind' => ContentKind::Navigation,
            'slug' => 'primary',
            'title' => 'Primary navigation',
            'template' => PageTemplate::Standard,
        ];
        $navigationPayload = [
            'title' => 'Primary navigation',
            'blocks' => [],
            'items' => [['label' => 'Retiring page', 'href' => '/primary']],
        ];

        $candidate = $lifecycle->validateCandidate(
            $page,
            $navigationIdentity,
            $navigationPayload,
            1,
            $user,
        );
        $this->assertArrayHasKey('/items/0', $candidate->errors());

        $revision = $page->revisions()->create([
            'revision_number' => 2,
            'schema_version' => 1,
            'identity' => [
                'kind' => ContentKind::Navigation->value,
                'slug' => 'primary',
                'title' => 'Primary navigation',
                'template' => PageTemplate::Standard->value,
            ],
            'payload' => $navigationPayload,
            'created_by' => $user->id,
            'source' => ContentSource::Admin,
        ]);

        foreach ([
            fn () => $lifecycle->publish($page->fresh(), $revision, 1, $user),
            fn () => $lifecycle->schedule(
                $page->fresh(),
                $revision,
                CarbonImmutable::now()->addDay(),
                1,
                $user,
            ),
        ] as $transition) {
            try {
                $transition();
                $this->fail('A post-transition site structure cannot link to its retired page path.');
            } catch (ContentValidationFailed $failure) {
                $this->assertArrayHasKey('/items/0', $failure->result->errors());
            }
        }

        $this->assertSame(ContentKind::Page, $page->fresh()->kind);
        $this->assertSame(ContentStatus::Published, $page->fresh()->status);
    }

    public function test_external_url_with_a_matching_path_does_not_block_archive(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $page = $lifecycle->createDraft(
            $this->identity(),
            $this->payload('External path lookalike.'),
            $user,
        );
        $page = $lifecycle->publish($page, $page->revisions()->sole(), 0, $user);
        $navigation = $lifecycle->createDraft([
            'kind' => ContentKind::Navigation,
            'slug' => 'primary',
            'title' => 'Primary navigation',
            'template' => PageTemplate::Standard,
        ], [
            'title' => 'Primary navigation',
            'blocks' => [],
            'items' => [[
                'label' => 'Partner about page',
                'href' => 'https://partner.example/about',
            ]],
        ], $user);
        $lifecycle->publish($navigation, $navigation->revisions()->sole(), 0, $user);

        $archived = $lifecycle->archive($page->fresh(), 1, $user);

        $this->assertSame(ContentStatus::Archived, $archived->status);
    }

    /**
     * @return array<string, ContentKind|PageTemplate|string>
     */
    private function identity(): array
    {
        return [
            'kind' => ContentKind::Page,
            'slug' => 'about',
            'title' => 'About',
            'template' => PageTemplate::Standard,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $body): array
    {
        return [
            'title' => 'About',
            'blocks' => [[
                'type' => 'content/direct-answer',
                'props' => [
                    'heading' => 'What should homeowners expect?',
                    'answer' => [['text' => $body]],
                ],
            ]],
        ];
    }

    private function media(): Media
    {
        return Media::create([
            'path' => 'media/inspection-camera.jpg',
            'original_name' => 'inspection-camera.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'width' => 1200,
            'height' => 800,
            'alt' => 'Technician preparing an inspection camera',
        ]);
    }
}
