<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Holds the code to docs/brand/design-system.md.
 *
 * The spec is the thing future agents read, and the theme is the thing the
 * site renders. These tests fail the moment the two disagree, so a design
 * change has to update both, together, on purpose.
 */
class DesignSystemContractTest extends TestCase
{
    public function test_every_color_in_the_spec_is_the_token_the_theme_defines(): void
    {
        $theme = $this->read('resources/css/theme.css');
        $colors = $this->specColors();

        $this->assertGreaterThanOrEqual(10, count($colors), 'The color table in the design spec could not be read.');

        foreach ($colors as $token => $hex) {
            $this->assertStringContainsString(
                $token.': '.$hex.';',
                $theme,
                "{$token} is {$hex} in docs/brand/design-system.md but not in resources/css/theme.css.",
            );
        }
    }

    public function test_editable_colors_ship_the_spec_defaults(): void
    {
        $config = require $this->path('config/theme.php');
        $colors = $this->specColors();

        foreach ($config['colors'] as $token => $color) {
            $this->assertArrayHasKey('--color-'.$token, $colors, "Editable color {$token} is missing from the design spec.");
            $this->assertSame($colors['--color-'.$token], $color['default'], "config/theme.php default for {$token} differs from the design spec.");
        }
    }

    public function test_the_brand_is_square_cornered(): void
    {
        $theme = $this->read('resources/css/theme.css');

        foreach (['control', 'card', 'panel', 'feature'] as $radius) {
            $this->assertMatchesRegularExpression(
                '/--radius-'.$radius.':\s*0;/',
                $theme,
                "--radius-{$radius} must be 0: the brand is square-cornered.",
            );
        }

        $this->assertStringContainsString('--radius-pill: 999px;', $theme);
    }

    public function test_styles_only_use_radius_tokens(): void
    {
        foreach ($this->stylesheets() as $path => $css) {
            preg_match_all('/border(?:-[a-z]+)*-radius:\s*([^;]+);/', $css, $matches);

            foreach ($matches[1] as $value) {
                $this->assertMatchesRegularExpression(
                    '/^(?:var\(--radius-[a-z]+\)|0|inherit)$/',
                    trim($value),
                    "{$path} uses a literal border-radius ({$value}). Use a --radius-* token.",
                );
            }
        }
    }

    public function test_one_font_family(): void
    {
        $theme = $this->read('resources/css/theme.css');

        $this->assertMatchesRegularExpression("/--font-heading:\s*'Outfit'/", $theme);
        $this->assertMatchesRegularExpression("/--font-body:\s*'Outfit'/", $theme);

        foreach ($this->stylesheets() as $path => $css) {
            preg_match_all('/font-family:\s*([^;]+);/', $css, $matches);

            foreach ($matches[1] as $value) {
                $this->assertMatchesRegularExpression(
                    '/^(?:var\(--font-(?:heading|body)\)|inherit|monospace|ui-monospace[^;]*)$/',
                    trim($value),
                    "{$path} sets font-family to {$value}. Use var(--font-heading) or var(--font-body).",
                );
            }
        }

        $package = json_decode($this->read('package.json'), true, flags: JSON_THROW_ON_ERROR);
        $fonts = array_filter(array_keys($package['dependencies'] ?? []), fn (string $name): bool => str_starts_with($name, '@fontsource/'));

        $this->assertSame(['@fontsource/outfit'], array_values($fonts), 'Only Outfit may be installed as a web font.');
    }

    public function test_brand_styles_use_tokens_rather_than_raw_colors(): void
    {
        foreach (glob($this->path('resources/css/brand/*.css')) as $file) {
            $css = (string) file_get_contents($file);

            $this->assertDoesNotMatchRegularExpression('/#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(/i', $css, basename($file).' contains a raw color.');
            $this->assertDoesNotMatchRegularExpression('/:\s*(?:white|black)\s*(?:;|,|\))/i', $css, basename($file).' contains a named color.');
        }
    }

    public function test_documented_button_variants_exist(): void
    {
        $css = implode("\n", $this->stylesheets());

        foreach (['button-orange', 'button-outline', 'button-sky', 'button-teal'] as $variant) {
            $this->assertStringContainsString('.'.$variant.' {', $css, "The documented .{$variant} button has no styles.");
        }
    }

    public function test_documented_brand_assets_exist(): void
    {
        $spec = $this->read('docs/brand/design-system.md');
        preg_match('/## Brand assets(.*?)\n## /s', $spec, $section);

        preg_match_all('/`((?:logo|icons|patterns|photos)[^`]*\.(?:png|jpg))`/', $section[1] ?? '', $files);

        $this->assertNotEmpty($files[1], 'The brand assets table could not be read.');

        foreach ($files[1] as $file) {
            if (str_contains($file, '*')) {
                $this->assertNotEmpty(glob($this->path('public/images/brand/'.$file)), "No files match {$file}.");

                continue;
            }

            $this->assertFileExists($this->path('public/images/brand/'.$file));
        }

        $config = require $this->path('config/theme.php');

        foreach ($config['logos'] as $variant => $logo) {
            $size = getimagesize($this->path('public/'.$logo['src']));

            $this->assertIsArray($size, "The {$variant} logo is missing.");
            $this->assertSame([$logo['width'], $logo['height']], [$size[0], $size[1]], "The {$variant} logo dimensions in config/theme.php are wrong.");
        }
    }

    public function test_every_feature_card_count_the_spec_allows_has_a_layout(): void
    {
        $spec = $this->read('docs/brand/design-system.md');
        $words = ['two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8];

        $this->assertMatchesRegularExpression('/`content\/feature-cards` takes (\w+) to (\w+) cards/', $spec, 'The spec no longer states the feature card range.');
        preg_match('/`content\/feature-cards` takes (\w+) to (\w+) cards/', $spec, $range);
        [$min, $max] = [$words[$range[1]] ?? 0, $words[$range[2]] ?? 0];

        $view = $this->read('resources/views/components/blocks/content/feature-cards.blade.php');
        $this->assertStringContainsString("->take({$max})", $view, 'The block renders a different number of cards than the spec allows.');
        $this->assertStringContainsString("->count() >= {$min}", $view, 'The block needs a different minimum than the spec states.');

        $css = $this->read('resources/css/brand/blocks.css');

        foreach (range($min, $max) as $count) {
            if ($count === 3) {
                continue; // Three columns is the list's default.
            }

            $this->assertStringContainsString(".feature-cards-list.is-count-{$count}", $css, "{$count} feature cards have no layout.");
        }
    }

    public function test_checklist_labels_are_weight_600_on_their_own_line(): void
    {
        $spec = $this->read('docs/brand/design-system.md');
        $css = $this->read('resources/css/brand/sections.css');

        $this->assertStringContainsString('`{label, text}`', $spec, 'The spec no longer documents labelled checklist items.');
        $this->assertMatchesRegularExpression('/\.check-list-label \{[^}]*display: block;[^}]*font-weight: 600;[^}]*\}/', $css);
    }

    /**
     * @return array<string, string> token => hex, from the spec's color table.
     */
    private function specColors(): array
    {
        $spec = $this->read('docs/brand/design-system.md');
        preg_match('/## Color(.*?)\n## /s', $spec, $section);
        preg_match_all('/^\| `(--color-[a-z-]+)`[^|]*\| `(#[0-9a-f]{6})` \|/m', $section[1] ?? '', $rows, PREG_SET_ORDER);

        $colors = [];

        foreach ($rows as $row) {
            $colors[$row[1]] = $row[2];
        }

        return $colors;
    }

    /**
     * @return array<string, string> path => contents of every stylesheet.
     */
    private function stylesheets(): array
    {
        $sheets = ['resources/css/app.css' => $this->read('resources/css/app.css')];

        foreach (glob($this->path('resources/css/brand/*.css')) as $file) {
            $sheets['resources/css/brand/'.basename($file)] = (string) file_get_contents($file);
        }

        return $sheets;
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($this->path($path));

        $this->assertNotFalse($contents, $path.' is missing.');

        return $contents;
    }

    private function path(string $path): string
    {
        return dirname(__DIR__, 2).'/'.$path;
    }
}
