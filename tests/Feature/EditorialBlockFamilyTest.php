<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EditorialBlockFamilyTest extends TestCase
{
    #[DataProvider('editorialBlockProvider')]
    public function test_editorial_blocks_render_their_semantic_contract(
        string $template,
        array $data,
        array $expected,
        array $unexpected = [],
    ): void {
        $html = Blade::render($template, $data);

        foreach ($expected as $needle) {
            $this->assertStringContainsString($needle, $html);
        }

        foreach ($unexpected as $needle) {
            $this->assertStringNotContainsString($needle, $html);
        }
    }

    public static function editorialBlockProvider(): iterable
    {
        yield 'inclusions and exclusions' => [
            '<x-blocks.content.inclusions-exclusions heading="Know the scope" :included="$included" :excluded="$excluded" />',
            [
                'included' => ['Supply registers', '', 42],
                'excluded' => ['Repairs unless approved', ''],
            ],
            ['Know the scope', 'Included', 'Supply registers', 'Not included', 'Repairs unless approved'],
            ['42'],
        ];

        yield 'comparison table' => [
            '<x-blocks.content.comparison-table heading="Compare options" caption="Fictional comparison" :columns="$columns" :rows="$rows" />',
            [
                'columns' => [
                    ['key' => 'basic', 'label' => 'Basic'],
                    ['key' => 'careful', 'label' => 'Careful'],
                ],
                'rows' => [
                    ['label' => 'Scope explained', 'values' => ['basic' => 'Sometimes', 'careful' => 'Yes']],
                    ['label' => '', 'values' => []],
                ],
            ],
            ['<caption>Fictional comparison</caption>', 'scope="col"', 'scope="row"', 'Scope explained', 'Careful'],
        ];

        yield 'table of contents' => [
            '<x-blocks.content.table-of-contents heading="On this page" :items="$items" />',
            ['items' => [
                ['label' => 'What is included', 'href' => '#what-is-included'],
                ['label' => 'Unsafe', 'href' => '/different-page'],
            ]],
            ['On this page', 'href="#what-is-included"'],
            ['Unsafe', '/different-page'],
        ];

        yield 'sources and reviewer' => [
            '<x-blocks.content.sources-reviewer heading="Sources and review" :sources="$sources" :reviewer="$reviewer" />',
            [
                'sources' => [
                    ['label' => 'Fictional source', 'publisher' => 'Example publisher', 'url' => 'https://example.com/source', 'reviewed_at' => '2026-07-01'],
                    ['label' => 'Unsafe', 'url' => 'javascript:alert(1)', 'reviewed_at' => 'not-a-date'],
                ],
                'reviewer' => ['name' => 'Jordan Example', 'role' => 'Technical reviewer', 'reviewed_at' => '2026-07-02'],
            ],
            ['Fictional source', 'Example publisher', 'datetime="2026-07-01"', 'Jordan Example', 'Technical reviewer', 'datetime="2026-07-02"'],
            ['Unsafe', 'javascript:'],
        ];

        yield 'related content' => [
            '<x-blocks.content.related-content heading="Keep learning" :items="$items" />',
            ['items' => [
                ['title' => 'Fictional guide', 'summary' => 'A useful next read.', 'href' => '/guides/fictional', 'type' => 'Guide'],
                ['title' => 'Unsafe guide', 'href' => 'javascript:alert(1)'],
            ]],
            ['Keep learning', 'Fictional guide', 'A useful next read.', 'href="/guides/fictional"', 'Guide'],
            ['Unsafe guide', 'javascript:'],
        ];

        yield 'pricing factors' => [
            '<x-blocks.pricing.factors heading="What affects a quote?" :factors="$factors" />',
            ['factors' => [
                ['label' => 'System size', 'description' => 'Larger systems can require more time.'],
                ['label' => '', 'description' => 'Invalid'],
            ]],
            ['What affects a quote?', 'System size', 'Larger systems can require more time.'],
            ['Invalid'],
        ];
    }
}
