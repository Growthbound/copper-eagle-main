<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ColorContrastTest extends TestCase
{
    public function test_semantic_accent_color_pairs_meet_wcag_aa_for_normal_text(): void
    {
        $tokens = $this->themeTokens();

        // White labels sit on copper buttons.
        $this->assertGreaterThanOrEqual(4.5, $this->contrast($tokens['orange'], $tokens['white']));
        $this->assertGreaterThanOrEqual(4.5, $this->contrast($tokens['orange-ink'], $tokens['white']));
        $this->assertGreaterThanOrEqual(4.5, $this->contrast($tokens['orange-light'], $tokens['teal']));
    }

    /**
     * Every text and background pair the design spec allows.
     */
    public function test_brand_surface_pairs_meet_wcag_aa_for_normal_text(): void
    {
        $tokens = $this->themeTokens();

        foreach ([
            ['white', 'teal'],
            ['white', 'copper-dark'],
            ['ink', 'white'],
            ['ink', 'mist'],
            ['ink', 'sky'],
            ['ink', 'stone'],
            ['teal', 'sky'],
            ['teal', 'mist'],
            ['muted', 'white'],
            ['muted', 'mist'],
            ['orange-ink', 'stone'],
        ] as [$text, $surface]) {
            $this->assertGreaterThanOrEqual(
                4.5,
                $this->contrast($tokens[$text], $tokens[$surface]),
                "{$text} text on {$surface} fails WCAG AA.",
            );
        }

        // The light end of the button gradient still clears the large-text bar.
        $this->assertGreaterThanOrEqual(3.0, $this->contrast($tokens['copper-light'], $tokens['white']));
    }

    /** @return array<string, string> */
    private function themeTokens(): array
    {
        $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/theme.css');
        preg_match_all('/--color-([a-z-]+):\s*(#[0-9a-f]{6});/i', $css, $matches, PREG_SET_ORDER);

        return collect($matches)->mapWithKeys(fn (array $match): array => [$match[1] => strtolower($match[2])])->all();
    }

    private function contrast(string $first, string $second): float
    {
        $lighter = max($this->luminance($first), $this->luminance($second));
        $darker = min($this->luminance($first), $this->luminance($second));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function luminance(string $hex): float
    {
        $channels = array_map(
            function (string $channel): float {
                $value = hexdec($channel) / 255;

                return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split(ltrim($hex, '#'), 2),
        );

        return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
    }
}
