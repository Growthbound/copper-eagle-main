<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The block catalog is the interface an agent navigates to build pages, so its
 * documentation is load-bearing rather than decorative. These tests fail when
 * the catalog drifts out of a state something can read reliably.
 */
class BlockCatalogTest extends TestCase
{
    private string $blocks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->blocks = dirname(__DIR__, 2).'/resources/views/components/blocks';
    }

    public function test_every_block_category_documents_itself(): void
    {
        foreach ($this->categories() as $category) {
            $this->assertFileExists(
                $this->blocks.'/'.$category.'/README.md',
                "The {$category} block folder has no README, so nothing can discover how to use it.",
            );
        }
    }

    public function test_the_catalog_index_lists_every_category(): void
    {
        $index = file_get_contents($this->blocks.'/README.md');

        foreach ($this->categories() as $category) {
            $this->assertStringContainsString(
                '`'.$category.'`',
                $index,
                "The {$category} category is missing from the catalog index.",
            );
        }
    }

    public function test_block_templates_declare_their_props(): void
    {
        foreach ($this->templates() as $template) {
            $this->assertStringContainsString(
                '@props(',
                file_get_contents($template),
                basename($template).' does not declare its inputs with @props.',
            );
        }
    }

    public function test_block_templates_use_theme_tokens_rather_than_literal_colors(): void
    {
        foreach ($this->templates() as $template) {
            $this->assertDoesNotMatchRegularExpression(
                '/#[0-9a-f]{3,8}\b|rgba?\(/i',
                file_get_contents($template),
                basename($template).' hard-codes a color, so it will not follow the site palette.',
            );
        }
    }

    public function test_block_templates_do_not_paste_raw_svg_icons(): void
    {
        foreach ($this->templates() as $template) {
            $this->assertDoesNotMatchRegularExpression(
                '/<svg\b/i',
                file_get_contents($template),
                basename($template).' pastes raw SVG instead of using the shared marketing icon catalog.',
            );
        }
    }

    public function test_block_templates_do_not_call_icon_packages_directly(): void
    {
        foreach ($this->templates() as $template) {
            $this->assertDoesNotMatchRegularExpression(
                '/<x-heroicon-[^>]*>/i',
                file_get_contents($template),
                basename($template).' bypasses the shared marketing icon wrapper.',
            );
        }
    }

    /** @return array<int, string> */
    private function categories(): array
    {
        return collect(glob($this->blocks.'/*', GLOB_ONLYDIR))
            ->map(fn (string $path): string => basename($path))
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private function templates(): array
    {
        return glob($this->blocks.'/*/*.blade.php') ?: [];
    }
}
