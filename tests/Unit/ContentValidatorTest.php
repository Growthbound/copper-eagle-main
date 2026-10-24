<?php

namespace Tests\Unit;

use App\Content\Area;
use App\Content\AreaRepository;
use App\Content\BlockData;
use App\Content\BlockRegistry;
use App\Content\ContentValidator;
use App\Content\Enums\ContentKind;
use App\Content\Enums\PageTemplate;
use App\Enums\MediaKind;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContentValidatorTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('reservedSlugProvider')]
    public function test_managed_pages_cannot_shadow_application_routes(string $slug): void
    {
        $result = app(ContentValidator::class)->validate(
            $this->identity($slug),
            $this->payload(),
        );

        $this->assertArrayHasKey('/identity/slug', $result->errors());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function reservedSlugProvider(): array
    {
        return [
            'templates' => ['templates'],
            'services' => ['services'],
            'service child' => ['services/example'],
            'areas' => ['areas'],
            'media' => ['media/1'],
            'images' => ['images'],
            'images child' => ['images/home'],
            'health' => ['up'],
            'robots' => ['robots.txt'],
            'llms' => ['llms.txt'],
            'sitemap' => ['sitemap.xml'],
            'preview' => ['content-preview/token'],
            'livewire' => ['livewire-d0224077/update'],
            'assets' => ['build/assets/app.css'],
        ];
    }

    public function test_only_content_kinds_with_a_real_consumer_are_supported(): void
    {
        $supported = [
            ContentKind::Page,
            ContentKind::Service,
            ContentKind::Location,
            ContentKind::Article,
            ContentKind::Navigation,
            ContentKind::Footer,
        ];

        foreach (ContentKind::cases() as $kind) {
            $result = app(ContentValidator::class)->validate(
                [...$this->identity('kind-'.$kind->value), 'kind' => $kind],
                $kind === ContentKind::Page
                    ? $this->payload()
                    : ['title' => 'Structure', 'blocks' => []],
            );

            $this->assertSame(
                in_array($kind, $supported, true),
                ! array_key_exists('/identity/kind', $result->errors()),
                $kind->value,
            );
        }
    }

    public function test_managed_content_rejects_inline_style_and_event_handler_keys(): void
    {
        $result = app(ContentValidator::class)->validate(
            $this->identity('unsafe-keys'),
            [
                ...$this->payload(),
                'style' => 'color:red',
                'nested' => ['onclick' => 'alert(1)'],
            ],
        );

        $this->assertArrayHasKey('/style', $result->errors());
        $this->assertArrayHasKey('/nested/onclick', $result->errors());
    }

    public function test_standard_pages_reject_multiple_h1_owning_blocks(): void
    {
        $result = app(ContentValidator::class)->validate(
            $this->identity('duplicate-hero'),
            [
                'title' => 'Duplicate hero',
                'blocks' => [
                    ['type' => 'hero/interior', 'props' => ['heading' => 'First']],
                    ['type' => 'hero/split', 'props' => ['heading' => 'Second']],
                ],
            ],
        );

        $this->assertArrayHasKey('/blocks', $result->errors());
    }

    public function test_standard_page_requires_its_only_hero_to_be_the_first_block(): void
    {
        $result = app(ContentValidator::class)->validate(
            $this->identity('late-hero'),
            [
                'title' => 'Late hero',
                'blocks' => [
                    [
                        'type' => 'content/direct-answer',
                        'props' => [
                            'heading' => 'Answer first',
                            'answer' => [['text' => 'This content must not precede the page H1.']],
                        ],
                    ],
                    [
                        'type' => 'hero/interior',
                        'props' => ['heading' => 'Late page heading'],
                    ],
                ],
            ],
        );

        $this->assertArrayHasKey('/blocks/1/type', $result->errors());
    }

    public function test_location_heroes_require_a_configured_service_area_name(): void
    {
        $this->useAreas([new Area('springfield', ['name' => 'Springfield'])]);

        foreach (['Atlantis', '123 Main Street'] as $place) {
            $result = app(BlockRegistry::class)->validate('hero/location', [
                'heading' => 'Service nearby',
                'place' => $place,
                'primaryAction' => ['label' => 'Book', 'href' => '#quote'],
            ]);

            $this->assertArrayHasKey('/props/place', $result->errors(), $place);
        }

        $valid = app(BlockRegistry::class)->validate('hero/location', [
            'heading' => 'Service in Springfield',
            'place' => 'Springfield',
            'primaryAction' => ['label' => 'Book', 'href' => '#quote'],
        ]);

        $this->assertArrayNotHasKey('/props/place', $valid->errors());
    }

    public function test_location_heroes_allow_every_exact_configured_service_area_name(): void
    {
        $areas = Mockery::mock(AreaRepository::class);
        $areas->shouldReceive('all')
            ->twice()
            ->andReturn(collect([
                new Area('st-george', ['name' => 'St. George']),
                new Area('district-9', ['name' => 'District 9']),
            ]));
        $this->app->instance(AreaRepository::class, $areas);
        $blocks = app(BlockRegistry::class);

        foreach (['St. George', 'District 9'] as $place) {
            $result = $blocks->validate('hero/location', [
                'heading' => "Service in {$place}",
                'place' => $place,
                'primaryAction' => ['label' => 'Book', 'href' => '#quote'],
            ]);

            $this->assertArrayNotHasKey('/props/place', $result->errors(), $place);
        }
    }

    public function test_nested_evidence_is_validated_with_exact_pointers(): void
    {
        $blocks = app(BlockRegistry::class);

        $cases = [
            ['photo-grid/gallery', ['heading' => 'Gallery', 'images' => [['src' => 'javascript:alert(1)']]], '/props/images/0'],
            ['before-after/slider', ['heading' => 'Before and after', 'before' => ['nope' => 1], 'after' => ['nope' => 2]], '/props/before'],
            ['media/video', ['heading' => 'Video', 'video' => ['src' => 'javascript:x'], 'poster' => ['x' => 1], 'transcript' => 'Transcript'], '/props/video'],
            ['team/grid', ['heading' => 'Team', 'members' => [['garbage' => true]]], '/props/members/0'],
            ['stats/proof', ['heading' => 'Proof', 'items' => [['junk' => 1]]], '/props/items/0'],
        ];

        foreach ($cases as [$type, $props, $pointer]) {
            $this->assertArrayHasKey(
                $pointer,
                $blocks->validate($type, $props)->errors(),
                $type,
            );
        }
    }

    public function test_invalid_service_area_is_reported_at_its_original_index(): void
    {
        $this->useAreas([new Area('springfield', ['name' => 'Springfield'])]);

        $result = app(BlockRegistry::class)->validate('service-areas/grid', [
            'heading' => 'Areas we serve',
            'areas' => [
                ['name' => '', 'href' => 'javascript:alert(1)'],
                // A governed area: it names an area record and links at that
                // record's own page.
                ['name' => 'Springfield', 'href' => '/areas/springfield'],
            ],
        ]);

        $this->assertArrayHasKey('/props/areas/0', $result->errors());
        $this->assertArrayNotHasKey('/props/areas/1', $result->errors());
    }

    public function test_ungoverned_service_area_and_malformed_collection_are_rejected(): void
    {
        $blocks = app(BlockRegistry::class);

        $ungoverned = $blocks->validate('service-areas/grid', [
            'heading' => 'Areas we serve',
            'areas' => [['name' => 'Gotham', 'href' => '/areas/gotham']],
        ]);
        $malformed = $blocks->validate('team/grid', [
            'heading' => 'Our team',
            'members' => ['name' => 'Not an ordered collection'],
        ]);

        $this->assertArrayHasKey('/props/areas/0', $ungoverned->errors());
        $this->assertArrayHasKey('/props/members', $malformed->errors());
    }

    public function test_article_hero_requires_a_renderer_safe_publication_date(): void
    {
        $result = app(BlockRegistry::class)->validate('hero/article', [
            'heading' => 'Helpful guide',
            'author' => 'Example Co.',
            'publishedAt' => 'not-a-date',
        ]);

        $this->assertArrayHasKey('/props/publishedAt', $result->errors());
    }

    /**
     * `updatedAt` once shipped with no direct coverage at all, which is how
     * the reviewer-ordering hole below survived.
     */
    public function test_article_hero_orders_its_dates_against_each_other(): void
    {
        $blocks = app(BlockRegistry::class);
        $base = ['heading' => 'Helpful guide', 'author' => 'Example Co.', 'publishedAt' => '2020-01-01'];

        $this->assertArrayHasKey(
            '/props/updatedAt',
            $blocks->validate('hero/article', $base + ['updatedAt' => '2019-06-01'])->errors(),
            'A revision dated before publication was accepted.',
        );

        $this->assertArrayHasKey(
            '/props/updatedAt',
            $blocks->validate('hero/article', $base + ['updatedAt' => '01/02/2026'])->errors(),
        );

        $this->assertFalse(
            $blocks->validate('hero/article', $base + ['updatedAt' => '2026-08-02'])->hasErrors(),
        );
    }

    /**
     * A reviewer endorses a version, not a URL. A rewrite dated after the
     * review would otherwise still render "Reviewed by …" beside the older
     * date, so the current text carries accountability nobody gave it.
     */
    public function test_a_review_older_than_the_revision_is_refused(): void
    {
        $blocks = app(BlockRegistry::class);
        $base = ['heading' => 'Helpful guide', 'author' => 'Example Co.', 'publishedAt' => '2020-01-01'];
        $reviewer = fn (string $date): array => ['name' => 'A Reviewer', 'reviewed_at' => $date];

        $this->assertArrayHasKey(
            '/props/reviewer/reviewed_at',
            $blocks->validate('hero/article', $base + [
                'updatedAt' => '2026-08-02',
                'reviewer' => $reviewer('2025-01-01'),
            ])->errors(),
        );

        foreach (['2026-08-02', '2026-09-01'] as $reviewedAt) {
            $this->assertFalse(
                $blocks->validate('hero/article', $base + [
                    'updatedAt' => '2026-08-02',
                    'reviewer' => $reviewer($reviewedAt),
                ])->hasErrors(),
                "A review on {$reviewedAt} should vouch for a revision dated 2026-08-02.",
            );
        }
    }

    public function test_page_metadata_rejects_non_boolean_noindex_values(): void
    {
        $payload = $this->payload();
        $payload['noindex'] = 'false';

        $result = app(ContentValidator::class)->validate(
            $this->identity('typed-metadata'),
            $payload,
        );

        $this->assertArrayHasKey('/noindex', $result->errors());
    }

    public function test_image_fields_reject_video_media_even_with_payload_dimensions(): void
    {
        $video = Media::query()->create([
            'path' => 'media/testimonial.mp4',
            'original_name' => 'testimonial.mp4',
            'mime_type' => 'video/mp4',
            'size' => 1024,
            'kind' => MediaKind::Video,
            'alt' => null,
            'width' => null,
            'height' => null,
        ]);
        $media = [
            'media_id' => $video->id,
            'alt' => 'Not an image',
            'width' => 1200,
            'height' => 800,
        ];
        $block = app(BlockRegistry::class)->validate('hero/service', [
            'heading' => 'Service',
            'primaryAction' => ['label' => 'Book', 'href' => '#quote'],
            'image' => $media,
        ]);
        $payload = $this->payload();
        $payload['blocks'] = [[
            'type' => 'content/rich-text',
            'props' => [
                'heading' => 'Guide',
                'nodes' => [[
                    'type' => 'image',
                    ...$media,
                ]],
            ],
        ]];
        $rich = app(ContentValidator::class)->validate(
            $this->identity('video-as-image'),
            $payload,
        );

        $this->assertArrayHasKey('/props/image', $block->errors());
        $this->assertArrayHasKey(
            '/blocks/0/props/nodes/0/media_id',
            $rich->errors(),
        );
    }

    public function test_managed_site_structure_rejects_missing_internal_destinations(): void
    {
        $validator = app(ContentValidator::class);
        $identity = [
            ...$this->identity('primary'),
            'kind' => ContentKind::Navigation,
        ];

        $missing = $validator->validate($identity, [
            'title' => 'Primary navigation',
            'blocks' => [],
            'items' => [['label' => 'Missing', 'href' => '/missing-page']],
        ]);
        $valid = $validator->validate($identity, [
            'title' => 'Primary navigation',
            'blocks' => [],
            'items' => [
                ['label' => 'Services', 'href' => '/services'],
                ['label' => 'Call', 'href' => 'tel:+15555550199'],
            ],
        ]);

        $this->assertArrayHasKey('/items/0', $missing->errors());
        $this->assertArrayNotHasKey('/items/0', $valid->errors());
        $this->assertArrayNotHasKey('/items/1', $valid->errors());
    }

    public function test_nested_schema_contracts_are_enforced_recursively(): void
    {
        $result = app(BlockRegistry::class)->validate('hero/interior', [
            'heading' => 'About our process',
            'primaryAction' => ['label' => 'Learn more'],
        ]);

        $this->assertArrayHasKey(
            '/props/primaryAction/href',
            $result->errors(),
        );
    }

    public function test_nested_object_properties_outside_the_contract_are_rejected(): void
    {
        $result = app(BlockRegistry::class)->validate('hero/interior', [
            'heading' => 'About our process',
            'primaryAction' => [
                'label' => 'Learn more',
                'href' => '/services',
                'tracking_id' => 'undeclared-field',
            ],
        ]);

        $this->assertArrayHasKey(
            '/props/primaryAction/tracking_id',
            $result->errors(),
        );
    }

    public function test_explicit_dynamic_object_properties_keep_their_declared_value_contract(): void
    {
        $valid = app(BlockRegistry::class)->validate('content/comparison-table', [
            'columns' => [[
                'key' => 'careful',
                'label' => 'Careful option',
            ]],
            'rows' => [[
                'label' => 'Approach',
                'values' => ['careful' => 'Documented process'],
            ]],
        ]);
        $invalid = app(BlockRegistry::class)->validate('content/comparison-table', [
            'columns' => [[
                'key' => 'careful',
                'label' => 'Careful option',
            ]],
            'rows' => [[
                'label' => 'Approach',
                'values' => ['careful' => 123],
            ]],
        ]);

        $this->assertArrayNotHasKey(
            '/props/rows/0/values/careful',
            $valid->errors(),
        );
        $this->assertArrayHasKey(
            '/props/rows/0/values/careful',
            $invalid->errors(),
        );
    }

    public function test_comparison_columns_require_unique_stable_keys(): void
    {
        $result = app(BlockRegistry::class)->validate('content/comparison-table', [
            'columns' => [
                ['key' => 'careful', 'label' => 'Careful'],
                ['key' => 'careful', 'label' => 'Also careful'],
            ],
            'rows' => [[
                'label' => 'Approach',
                'values' => ['careful' => 'Documented process'],
            ]],
        ]);

        $this->assertArrayHasKey('/props/columns/1/key', $result->errors());
    }

    public function test_process_icons_must_come_from_the_shared_catalog(): void
    {
        $result = app(BlockRegistry::class)->validate('process-steps/icon-grid', [
            'heading' => 'How it works',
            'steps' => [[
                'icon' => 'made-up-icon',
                'title' => 'Inspect',
                'body' => 'We inspect the system.',
            ]],
        ]);

        $this->assertArrayHasKey('/props/steps/0/icon', $result->errors());
    }

    public function test_direct_answer_supporting_segments_use_the_shared_inline_contract(): void
    {
        $result = app(BlockRegistry::class)->validate('content/direct-answer', [
            'heading' => 'What should I know?',
            'answer' => [['text' => 'A direct answer.']],
            'supporting' => [[
                'text' => 'Unsafe supporting link',
                'href' => 'javascript:alert(1)',
            ]],
        ]);

        $this->assertArrayHasKey('/props/supporting/0/href', $result->errors());
    }

    public function test_catalog_media_urls_must_resolve_to_an_existing_record(): void
    {
        $payload = $this->payload();
        $payload['blocks'] = [[
            'type' => 'photo-grid/gallery',
            'props' => [
                'heading' => 'Documented project',
                'images' => [[
                    'src' => '/media/999999',
                    'alt' => 'Documented project',
                    'width' => 1200,
                    'height' => 800,
                ]],
            ],
        ]];

        $result = app(ContentValidator::class)->validate(
            $this->identity('missing-catalog-media-url'),
            $payload,
        );

        $this->assertArrayHasKey('/blocks/0/props/images/0/src', $result->errors());
    }

    public function test_catalog_media_urls_must_match_the_required_media_kind(): void
    {
        $video = Media::query()->create([
            'path' => 'media/project.mp4',
            'original_name' => 'project.mp4',
            'mime_type' => 'video/mp4',
            'size' => 1024,
            'kind' => MediaKind::Video,
        ]);
        $image = Media::query()->create([
            'path' => 'media/project.jpg',
            'original_name' => 'project.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'kind' => MediaKind::Image,
            'alt' => 'Project documentation',
            'width' => 1200,
            'height' => 800,
        ]);
        $payload = $this->payload();
        $payload['blocks'] = [
            [
                'type' => 'photo-grid/gallery',
                'props' => [
                    'heading' => 'Documented project',
                    'images' => [[
                        'src' => "/media/{$video->id}",
                        'alt' => 'Project documentation',
                        'width' => 1200,
                        'height' => 800,
                    ]],
                ],
            ],
            [
                'type' => 'media/video',
                'props' => [
                    'heading' => 'Project video',
                    'video' => [
                        'src' => "/media/{$image->id}",
                        'type' => 'video/mp4',
                    ],
                    'poster' => [
                        'media_id' => $image->id,
                        'alt' => 'Project documentation',
                    ],
                    'transcript' => 'A complete transcript.',
                ],
            ],
        ];

        $result = app(ContentValidator::class)->validate(
            $this->identity('wrong-catalog-media-kind'),
            $payload,
        );

        $this->assertArrayHasKey('/blocks/0/props/images/0/src', $result->errors());
        $this->assertArrayHasKey('/blocks/1/props/video/src', $result->errors());
    }

    public function test_every_controlled_optional_action_rejects_unsafe_links(): void
    {
        $cases = [
            ['content/direct-answer', [
                'heading' => 'Answer',
                'answer' => [['text' => 'Direct answer.']],
                'source' => ['label' => 'Source', 'href' => 'javascript:alert(1)'],
            ], '/props/source'],
            ['cta/inline-band', [
                'heading' => 'Next step',
                'primaryAction' => ['label' => 'Book', 'href' => '#quote'],
                'secondaryAction' => ['label' => 'Unsafe', 'href' => 'javascript:alert(1)'],
            ], '/props/secondaryAction'],
            ['hero/interior', [
                'heading' => 'Interior page',
                'primaryAction' => ['label' => 'Unsafe', 'href' => 'javascript:alert(1)'],
            ], '/props/primaryAction'],
            ['hero/service', [
                'heading' => 'Service',
                'primaryAction' => ['label' => 'Book', 'href' => '#quote'],
                'secondaryAction' => ['label' => 'Unsafe', 'href' => 'javascript:alert(1)'],
            ], '/props/secondaryAction'],
            ['hero/location', [
                'heading' => 'Service in Springfield',
                'place' => 'Springfield',
                'primaryAction' => ['label' => 'Book', 'href' => '#quote'],
                'secondaryAction' => ['label' => 'Unsafe', 'href' => 'javascript:alert(1)'],
            ], '/props/secondaryAction'],
        ];

        foreach ($cases as [$type, $props, $pointer]) {
            $this->assertArrayHasKey(
                $pointer,
                app(BlockRegistry::class)->validate($type, $props)->errors(),
                $type,
            );
        }
    }

    public function test_reviewer_contract_requires_renderer_safe_metadata(): void
    {
        $result = app(BlockRegistry::class)->validate('content/sources-reviewer', [
            'heading' => 'Sources and review',
            'sources' => [[
                'label' => 'Reference',
                'url' => 'https://example.com/reference',
                'reviewed_at' => '2026-07-26',
            ]],
            'reviewer' => [
                'name' => 'Taylor Example',
                'reviewed_at' => 'not-a-date',
            ],
        ]);

        $this->assertArrayHasKey('/props/reviewer/role', $result->errors());
        $this->assertArrayHasKey('/props/reviewer/reviewed_at', $result->errors());

        $schema = app(BlockRegistry::class)
            ->definition('content/sources-reviewer')
            ->fields['reviewer']['properties'];
        $this->assertTrue($schema['role']['required']);
        $this->assertSame('date', $schema['reviewed_at']['format']);
    }

    public function test_numbered_process_steps_reject_unused_icon_metadata(): void
    {
        $result = app(BlockRegistry::class)->validate('process-steps/grid', [
            'heading' => 'How it works',
            'steps' => [[
                'number' => '1',
                'icon' => 'calendar',
                'title' => 'Schedule',
                'body' => 'Choose a convenient time.',
            ]],
        ]);

        $this->assertArrayHasKey('/props/steps/0/icon', $result->errors());
    }

    public function test_root_relative_canonical_uses_the_shared_safe_url_contract(): void
    {
        foreach (["/guide\nother", '/\\evil'] as $canonical) {
            $payload = $this->payload();
            $payload['canonical'] = $canonical;
            $result = app(ContentValidator::class)->validate(
                $this->identity('unsafe-canonical'),
                $payload,
            );

            $this->assertArrayHasKey('/canonical', $result->errors());
        }
    }

    public function test_block_properties_outside_the_machine_readable_contract_are_rejected(): void
    {
        $result = app(BlockRegistry::class)->validate('faq/highlighted', [
            'heading' => 'Questions',
            'items' => [[
                'question' => 'What happens next?',
                'answer' => 'We explain the next step clearly.',
            ]],
            'open-index' => 'not-an-integer',
        ]);

        $this->assertArrayHasKey('/props/open-index', $result->errors());
        $this->assertArrayNotHasKey('/props/openIndex', $result->errors());
    }

    public function test_video_testimonials_use_the_video_contract_instead_of_quote_fields(): void
    {
        $poster = Media::query()->create([
            'path' => 'media/customer-video-poster.jpg',
            'original_name' => 'customer-video-poster.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'kind' => MediaKind::Image,
            'alt' => 'Customer preparing to share a service story',
            'width' => 1280,
            'height' => 720,
        ]);
        $blocks = app(BlockRegistry::class);
        $video = $blocks->validate('testimonials/carousel', [
            'heading' => 'Customer stories',
            'items' => [[
                'type' => 'video',
                'title' => 'A customer video',
                'context' => 'Verified customer',
                'video_url' => 'https://video.example/customer-story',
                'transcript' => 'The customer describes the completed service and the explanation they received.',
                'poster' => [
                    'media_id' => $poster->id,
                    'alt' => 'Customer preparing to share a service story',
                ],
            ]],
        ]);
        $quote = $blocks->validate('testimonials/carousel', [
            'heading' => 'Customer stories',
            'items' => [[
                'type' => 'quote',
                'quote' => 'Careful work.',
            ]],
        ]);

        $this->assertTrue($video->isValid(), json_encode($video->errors()));
        $this->assertArrayHasKey('/props/items/0/author', $quote->errors());

        $schema = $blocks->definition('testimonials/carousel')->fields['items']['items']['properties'];
        $this->assertArrayHasKey('video_url', $schema);
        $this->assertArrayHasKey('poster', $schema);
        $this->assertArrayHasKey('transcript', $schema);
    }

    public function test_video_testimonials_require_a_transcript(): void
    {
        $poster = Media::query()->create([
            'path' => 'media/customer-video-poster.jpg',
            'original_name' => 'customer-video-poster.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'kind' => MediaKind::Image,
            'alt' => 'Customer preparing to share a service story',
            'width' => 1280,
            'height' => 720,
        ]);

        $result = app(BlockRegistry::class)->validate('testimonials/carousel', [
            'heading' => 'Customer stories',
            'items' => [[
                'type' => 'video',
                'title' => 'A customer video',
                'video_url' => 'https://video.example/customer-story',
                'poster' => ['media_id' => $poster->id],
            ]],
        ]);

        $this->assertArrayHasKey('/props/items/0/transcript', $result->errors());
    }

    public function test_video_testimonials_reject_non_media_link_forms(): void
    {
        $poster = Media::query()->create([
            'path' => 'media/customer-video-poster.jpg',
            'original_name' => 'customer-video-poster.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'kind' => MediaKind::Image,
            'alt' => 'Customer preparing to share a service story',
            'width' => 1280,
            'height' => 720,
        ]);

        foreach (['#video', 'mailto:owner@example.com', 'tel:+15555550199'] as $src) {
            $result = app(BlockRegistry::class)->validate('testimonials/carousel', [
                'heading' => 'Customer stories',
                'items' => [[
                    'type' => 'video',
                    'title' => 'A customer video',
                    'video_url' => $src,
                    'transcript' => 'A complete transcript.',
                    'poster' => ['media_id' => $poster->id],
                ]],
            ]);

            $this->assertArrayHasKey('/props/items/0/video_url', $result->errors(), $src);
        }
    }

    public function test_media_video_sources_reject_non_media_link_forms(): void
    {
        $blocks = app(BlockRegistry::class);

        foreach (['#video', 'mailto:owner@example.com', 'tel:+15555550199'] as $src) {
            $result = $blocks->validate('media/video', [
                'heading' => 'Customer story',
                'video' => ['src' => $src, 'type' => 'video/mp4'],
                'poster' => [
                    'src' => '/images/home/family-living-room.jpg',
                    'alt' => 'Customer preparing to share a story',
                    'width' => 1600,
                    'height' => 900,
                ],
                'transcript' => 'A complete transcript.',
            ]);

            $this->assertArrayHasKey('/props/video', $result->errors(), $src);
        }
    }

    public function test_resource_card_links_are_validated_when_present(): void
    {
        $result = app(BlockRegistry::class)->validate('content/resource-cards', [
            'heading' => 'Helpful resources',
            'items' => [[
                'title' => 'Unsafe destination',
                'body' => 'This destination must be rejected before publication.',
                'href' => 'javascript:alert(1)',
            ]],
        ]);

        $this->assertArrayHasKey('/props/items/0/href', $result->errors());
    }

    public function test_related_content_requires_an_existing_public_destination(): void
    {
        $blocks = app(BlockRegistry::class);
        $missing = $blocks->validate('content/related-content', [
            'heading' => 'Keep learning',
            'items' => [[
                'title' => 'Missing guide',
                'href' => '/missing-page',
            ]],
        ]);
        $existing = $blocks->validate('content/related-content', [
            'heading' => 'Keep learning',
            'items' => [[
                'title' => 'Browse services',
                'href' => '/services',
            ]],
        ]);

        $this->assertArrayHasKey('/props/items/0', $missing->errors());
        $this->assertArrayNotHasKey('/props/items/0', $existing->errors());
    }

    public function test_fragment_actions_must_target_one_rendered_page_id(): void
    {
        $validator = app(ContentValidator::class);
        $invalid = $validator->validate(
            $this->identity('dead-fragment-action'),
            [
                'title' => 'Dead fragment action',
                'blocks' => [[
                    'type' => 'hero/interior',
                    'props' => [
                        'heading' => 'A page heading',
                        'primaryAction' => ['label' => 'Request a visit', 'href' => '#quote'],
                    ],
                ]],
            ],
        );
        $valid = $validator->validate(
            $this->identity('live-fragment-action'),
            [
                'title' => 'Live fragment action',
                'blocks' => [
                    [
                        'type' => 'hero/interior',
                        'props' => [
                            'heading' => 'A page heading',
                            'primaryAction' => ['label' => 'Request a visit', 'href' => '#quote'],
                        ],
                    ],
                    [
                        'type' => 'cta/quote-form',
                        'props' => [
                            'id' => 'quote',
                            'heading' => 'Request a visit',
                        ],
                    ],
                ],
            ],
        );

        $this->assertArrayHasKey('/blocks/0/props/primaryAction/href', $invalid->errors());
        $this->assertArrayNotHasKey('/blocks/0/props/primaryAction/href', $valid->errors());
    }

    public function test_nested_livewire_component_ids_follow_each_block_anchor(): void
    {
        $blocks = app(BlockRegistry::class);

        $first = $blocks->emittedIds('cta/quote-form', [
            'id' => 'first-quote',
            'heading' => 'First quote',
        ]);
        $second = $blocks->emittedIds('cta/quote-form-split', [
            'id' => 'second-quote',
            'heading' => 'Second quote',
            'showMessage' => false,
        ]);
        $benefits = $blocks->emittedIds('content/benefit-carousel', [
            'id' => 'service-benefits',
            'heading' => 'Benefits',
        ]);

        $this->assertContains('first-quote-form-highlevel', $first);
        $this->assertContains('second-quote-form-highlevel', $second);
        $this->assertContains('first-quote-form-name', $first);
        $this->assertContains('first-quote-form-message', $first);
        $this->assertContains('second-quote-form-name', $second);
        $this->assertNotContains('second-quote-form-message', $second);
        $this->assertContains('service-benefits-title', $benefits);
        $this->assertSame(1, array_count_values($benefits)['service-benefits-title']);
        $this->assertSame([], array_values(array_intersect($first, $second)));
    }

    public function test_standard_page_validates_effective_anchor_syntax_uniqueness_and_toc_targets(): void
    {
        $validator = app(ContentValidator::class);
        $duplicateDefaults = $validator->validate(
            $this->identity('duplicate-default-anchors'),
            [
                'title' => 'Duplicate anchors',
                'blocks' => [
                    [
                        'type' => 'content/direct-answer',
                        'props' => [
                            'heading' => 'First answer',
                            'answer' => [['text' => 'First.']],
                        ],
                    ],
                    [
                        'type' => 'content/direct-answer',
                        'props' => [
                            'heading' => 'Second answer',
                            'answer' => [['text' => 'Second.']],
                        ],
                    ],
                ],
            ],
        );
        $invalidAndMissing = $validator->validate(
            $this->identity('invalid-toc-target'),
            [
                'title' => 'Invalid anchors',
                'blocks' => [
                    [
                        'type' => 'content/direct-answer',
                        'props' => [
                            'id' => 'Bad Anchor',
                            'heading' => 'Answer',
                            'answer' => [['text' => 'Answer.']],
                        ],
                    ],
                    [
                        'type' => 'content/table-of-contents',
                        'props' => [
                            'heading' => 'On this page',
                            'items' => [[
                                'label' => 'Missing section',
                                'href' => '#missing-section',
                            ]],
                        ],
                    ],
                ],
            ],
        );
        $valid = $validator->validate(
            $this->identity('valid-toc-target'),
            [
                'title' => 'Valid anchors',
                'blocks' => [
                    [
                        'type' => 'content/direct-answer',
                        'props' => [
                            'id' => 'direct-response',
                            'heading' => 'Answer',
                            'answer' => [['text' => 'Answer.']],
                        ],
                    ],
                    [
                        'type' => 'content/table-of-contents',
                        'props' => [
                            'heading' => 'On this page',
                            'items' => [[
                                'label' => 'Direct response',
                                'href' => '#direct-response',
                            ]],
                        ],
                    ],
                ],
            ],
        );

        $this->assertArrayHasKey('/blocks/1/props/id', $duplicateDefaults->errors());
        $this->assertArrayHasKey('/blocks/0/props/id', $invalidAndMissing->errors());
        $this->assertArrayHasKey(
            '/blocks/1/props/items/0/href',
            $invalidAndMissing->errors(),
        );
        $this->assertTrue($valid->isValid(), json_encode($valid->errors()));
    }

    public function test_standard_page_rejects_collisions_with_renderer_generated_heading_ids(): void
    {
        $result = app(ContentValidator::class)->validate(
            $this->identity('generated-heading-collision'),
            [
                'title' => 'Generated heading collision',
                'blocks' => [
                    [
                        'type' => 'faq/accordion',
                        'props' => [
                            'id' => 'faq',
                            'heading' => 'Questions',
                            'items' => [[
                                'question' => 'What happens?',
                                'answer' => 'The rendered heading owns faq-title.',
                            ]],
                        ],
                    ],
                    [
                        'type' => 'content/direct-answer',
                        'props' => [
                            'id' => 'faq-title',
                            'heading' => 'Direct answer',
                            'answer' => [['text' => 'This section must not reuse that ID.']],
                        ],
                    ],
                ],
            ],
        );

        $this->assertArrayHasKey('/blocks/1/props/id', $result->errors());
    }

    public function test_site_structure_kinds_require_the_consumed_singleton_identity(): void
    {
        $validator = app(ContentValidator::class);
        $navigation = $validator->validate([
            'kind' => ContentKind::Navigation,
            'slug' => 'secondary',
            'title' => 'Secondary navigation',
            'template' => PageTemplate::Standard,
        ], [
            'title' => 'Secondary navigation',
            'blocks' => [],
            'items' => [],
        ]);
        $footer = $validator->validate([
            'kind' => ContentKind::Footer,
            'slug' => 'site-footer',
            'title' => 'Footer',
            'template' => PageTemplate::ServicePillar,
        ], [
            'title' => 'Footer',
            'sections' => [],
        ]);

        $this->assertArrayHasKey('/identity/slug', $navigation->errors());
        $this->assertArrayHasKey('/identity/template', $footer->errors());
    }

    public function test_referenced_media_uses_catalog_dimensions_and_verified_provenance(): void
    {
        $media = Media::query()->create([
            'path' => 'media/catalog-image.jpg',
            'original_name' => 'catalog-image.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'kind' => MediaKind::Image,
            'alt' => 'Catalog image',
            'width' => 640,
            'height' => 480,
            'source_url' => null,
        ]);
        $data = app(BlockData::class);
        $resolved = $data->media([
            'media_id' => $media->id,
            'alt' => 'Contextual description',
            'width' => 1200,
            'height' => 800,
        ]);
        $forgedSource = $data->media([
            'media_id' => $media->id,
            'alt' => 'Contextual description',
            'source_label' => 'Invented evidence',
            'source_url' => 'https://example.com/invented',
        ], requireSource: true);
        $undimensioned = Media::query()->create([
            'path' => 'media/undimensioned.jpg',
            'original_name' => 'undimensioned.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'kind' => MediaKind::Image,
            'alt' => 'Undimensioned catalog image',
            'width' => null,
            'height' => null,
        ]);
        $forgedDimensions = $data->media([
            'media_id' => $undimensioned->id,
            'alt' => 'Contextual description',
            'width' => 1200,
            'height' => 800,
        ]);

        $this->assertSame(640, $resolved['width']);
        $this->assertSame(480, $resolved['height']);
        $this->assertNull($forgedSource);
        $this->assertNull($forgedDimensions);
    }

    /**
     * Area validity comes only from the area records, so tests about it bind
     * their own rather than reading whatever content/areas holds.
     *
     * @param  array<int, Area>  $areas
     */
    private function useAreas(array $areas): void
    {
        $repository = Mockery::mock(AreaRepository::class);
        $repository->shouldReceive('all')->andReturn(collect($areas));
        $this->app->instance(AreaRepository::class, $repository);
        $this->app->forgetInstance(BlockRegistry::class);
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
    private function payload(): array
    {
        return [
            'title' => 'Managed page',
            'blocks' => [[
                'type' => 'content/direct-answer',
                'props' => [
                    'heading' => 'What should I know?',
                    'answer' => [['text' => 'A direct answer.']],
                ],
            ]],
        ];
    }
}
