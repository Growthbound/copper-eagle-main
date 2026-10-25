<?php

namespace Tests\Unit;

use App\Content\BlockRegistry;
use App\Content\ContentSchemaDocument;
use App\Content\Enums\ContentKind;
use App\Content\Enums\ContentSource;
use App\Content\Enums\ContentStatus;
use App\Content\Enums\PageTemplate;
use App\Content\PageTemplateRegistry;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Tests\TestCase;

class ContentContractRegistryTest extends TestCase
{
    public function test_registries_expose_only_known_blocks_and_fixed_templates(): void
    {
        $blocks = app(BlockRegistry::class);
        $templates = app(PageTemplateRegistry::class);

        $this->assertSame(
            'blocks.content.rich-text',
            $blocks->component('content/rich-text'),
        );
        $this->assertNull($blocks->definition('../../admin'));
        $this->assertSame(
            ['standard', 'service-pillar', 'location-pillar', 'decision-guide', 'home', 'services-hub', 'service-detail', 'emergency', 'about', 'team', 'reviews', 'contact'],
            array_column(PageTemplate::cases(), 'value'),
        );
        $this->assertSame(
            'hero/service',
            $templates->definition(PageTemplate::ServicePillar)->sequence[0],
        );
    }

    public function test_unknown_block_cannot_select_a_blade_component(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(BlockRegistry::class)->component('../../admin');
    }

    public function test_registries_validate_required_fields_and_fixed_template_sections(): void
    {
        $blocks = app(BlockRegistry::class);
        $templates = app(PageTemplateRegistry::class);

        $blockResult = $blocks->validate('content/direct-answer', [
            'id' => 'what-is-air-duct-cleaning',
        ]);
        $templateResult = $templates->validate(PageTemplate::ServicePillar, [
            'hero/service' => [],
        ]);

        $this->assertFalse($blockResult->isValid());
        $this->assertSame(
            ['The heading field is required.', 'The answer field is required.'],
            $blockResult->errors()['/props'],
        );
        $this->assertFalse($templateResult->isValid());
        $this->assertArrayHasKey('/sections/trust-proof', $templateResult->errors());
    }

    public function test_schema_document_exposes_stable_agent_readable_contracts(): void
    {
        $schema = app(ContentSchemaDocument::class)->toArray();

        $this->assertSame(1, $schema['schema_version']);
        $this->assertSame(
            ['page', 'service', 'location', 'article', 'navigation', 'footer'],
            $schema['enums']['content_kinds'],
        );
        $this->assertSame(
            ['draft', 'published', 'scheduled', 'archived'],
            $schema['enums']['content_statuses'],
        );
        $this->assertSame(
            ['admin', 'api', 'import'],
            $schema['enums']['content_sources'],
        );
        $this->assertSame(
            ContentKind::Page->value,
            $schema['enums']['content_kinds'][0],
        );
        $this->assertSame(
            ContentStatus::Draft->value,
            $schema['enums']['content_statuses'][0],
        );
        $this->assertSame(
            ContentSource::Admin->value,
            $schema['enums']['content_sources'][0],
        );
        $this->assertSame(
            'blocks.content.rich-text',
            $schema['blocks']['content/rich-text']['component'],
        );
        $this->assertSame(
            'page-templates.service-pillar',
            $schema['page_templates']['service-pillar']['view'],
        );
    }

    public function test_live_schema_exposes_every_blade_prop_and_nested_array_contract(): void
    {
        $definitions = app(BlockRegistry::class)->all();

        foreach (File::allFiles(resource_path('views/components/blocks')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = $file->getContents();
            preg_match('/@props\(\[(.*?)\]\)/s', $contents, $props);

            if (! isset($props[1])) {
                continue;
            }

            preg_match_all(
                "/^ {4}'([A-Za-z][A-Za-z0-9_]*)'(?:\\s*=>|,)/m",
                $props[1],
                $matches,
            );
            $type = str($file->getRelativePathname())
                ->beforeLast('.blade.php')
                ->replace(DIRECTORY_SEPARATOR, '/')
                ->toString();
            $fields = $definitions[$type]->fields;

            foreach ($matches[1] as $prop) {
                $this->assertArrayHasKey(
                    $prop,
                    $fields,
                    "{$type} omits renderer prop {$prop} from the live schema.",
                );
            }

            foreach ($fields as $field => $schema) {
                if (($schema['type'] ?? null) !== 'array') {
                    continue;
                }

                $this->assertContains(
                    $schema['shape'] ?? null,
                    ['list', 'object'],
                    "{$type}.{$field} must expose its nested array shape.",
                );
                $this->assertArrayHasKey(
                    $schema['shape'] === 'list' ? 'items' : 'properties',
                    $schema,
                    "{$type}.{$field} must expose its nested contract.",
                );
            }
        }

        $service = $definitions['hero/service']->fields;
        $quote = $definitions['cta/quote-form-split']->fields;

        $this->assertSame('string', $service['body']['type']);
        $this->assertSame('object', $service['secondaryAction']['shape']);
        $this->assertSame('object', $service['image']['shape']);
        $this->assertSame('string', $quote['submitLabel']['type']);
        $this->assertSame('boolean', $quote['showMessage']['type']);
        $this->assertTrue(
            $definitions['process-steps/grid']
                ->fields['steps']['items']['properties']['number']['required'],
        );
    }

    public function test_structured_block_records_are_rejected_before_the_renderer_can_index_them(): void
    {
        $blocks = app(BlockRegistry::class);

        $pricing = $blocks->validate('pricing/toggle', ['plans' => [[]]]);
        $faq = $blocks->validate('faq/accordion', [
            'heading' => 'Questions',
            'items' => [[]],
        ]);
        $hero = $blocks->validate('hero/split', [
            'heading' => 'Safe heading',
            'image' => ['src' => 'missing-metadata.jpg'],
        ]);

        $this->assertFalse($pricing->isValid());
        $this->assertArrayHasKey('/props/plans/0', $pricing->errors());
        $this->assertFalse($faq->isValid());
        $this->assertArrayHasKey('/props/items/0', $faq->errors());
        $this->assertFalse($hero->isValid());
        $this->assertArrayHasKey('/props/image', $hero->errors());
    }

    public function test_announcement_bar_rejects_unsafe_link_schemes(): void
    {
        $result = app(BlockRegistry::class)->validate('announcement/bar', [
            'message' => 'Seasonal service update',
            'url' => 'javascript:alert(document.domain)',
            'linkLabel' => 'Read the update',
        ]);

        $this->assertArrayHasKey('/props/url', $result->errors());
    }

    public function test_block_media_rejects_link_only_urls_as_image_sources(): void
    {
        $blocks = app(BlockRegistry::class);

        foreach (['#quote', 'mailto:owner@example.com', 'tel:+18005550199'] as $src) {
            $result = $blocks->validate('hero/service', [
                'heading' => 'Service hero',
                'image' => [
                    'src' => $src,
                    'alt' => 'Technician inspecting ductwork',
                    'width' => 1200,
                    'height' => 800,
                ],
            ]);

            $this->assertArrayHasKey('/props/image', $result->errors(), $src);
        }
    }

    public function test_required_block_strings_reject_whitespace_only_values(): void
    {
        $result = app(BlockRegistry::class)->validate('hero/split', [
            'heading' => " \n\t ",
        ]);

        $this->assertSame(
            ['The heading field is required.'],
            $result->errors()['/props'] ?? null,
        );
    }

    public function test_managed_block_components_never_use_raw_blade_output(): void
    {
        foreach (app(BlockRegistry::class)->all() as $definition) {
            $path = resource_path(
                'views/components/'.str_replace('.', '/', $definition->component).'.blade.php',
            );

            $this->assertFileExists($path);
            $this->assertStringNotContainsString(
                '{!!',
                File::get($path),
                "{$definition->type} renders managed content through raw Blade output.",
            );
        }
    }

    public function test_fixed_templates_validate_default_anchor_collisions_and_toc_targets(): void
    {
        $templates = app(PageTemplateRegistry::class);
        $service = $templates->validate(PageTemplate::ServicePillar, [
            'problem-stakes' => [
                'heading' => 'Problem',
                'nodes' => [],
            ],
            'story-solution' => [
                'heading' => 'Solution',
                'nodes' => [],
            ],
        ]);
        $guide = $templates->validate(PageTemplate::DecisionGuide, [
            'content/table-of-contents' => [
                'heading' => 'On this page',
                'items' => [[
                    'label' => 'Missing section',
                    'href' => '#missing-section',
                ]],
            ],
            'context' => [
                'id' => 'guide-context',
                'heading' => 'Context',
                'nodes' => [],
            ],
        ]);

        $this->assertArrayHasKey('/sections/story-solution/id', $service->errors());
        $this->assertArrayHasKey(
            '/sections/content/table-of-contents/items/0/href',
            $guide->errors(),
        );
    }
}
