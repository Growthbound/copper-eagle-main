<?php

namespace App\Support;

use App\Models\Setting;

class Theme
{
    /**
     * Prefix used for every theme color stored in the settings table.
     */
    public const PREFIX = 'theme.color.';

    /**
     * The color each token falls back to when nothing has been saved.
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return array_map(
            fn (array $color): string => $color['default'],
            config('theme.colors'),
        );
    }

    /**
     * The colors the site is currently rendering with.
     *
     * @return array<string, string>
     */
    public static function palette(): array
    {
        $saved = Setting::values();

        $palette = [];

        foreach (static::defaults() as $token => $default) {
            $value = $saved[self::PREFIX.$token] ?? null;

            $palette[$token] = static::isHex($value) ? strtolower($value) : $default;
        }

        return $palette + config('theme.fixed');
    }

    /**
     * A :root block overriding only the tokens that differ from the defaults.
     *
     * Returns an empty string when the site is running stock colors, so the
     * marketing pages ship no extra markup until the owner customizes them.
     */
    public static function cssOverrides(): string
    {
        $defaults = static::defaults();

        $declarations = [];

        foreach (static::palette() as $token => $value) {
            if (($defaults[$token] ?? null) !== null && $defaults[$token] !== $value) {
                $declarations[] = '--color-'.$token.':'.$value;
            }
        }

        return $declarations === []
            ? ''
            : ':root{'.implode(';', $declarations).'}';
    }

    /**
     * @param  array<string, string>  $colors
     */
    public static function save(array $colors): void
    {
        $values = [];

        foreach ($colors as $token => $value) {
            if (! array_key_exists($token, config('theme.colors')) || ! static::isHex($value)) {
                continue;
            }

            $values[self::PREFIX.$token] = strtolower($value);
        }

        Setting::write($values);
    }

    public static function reset(): void
    {
        Setting::erase(...array_map(
            fn (string $token): string => self::PREFIX.$token,
            array_keys(config('theme.colors')),
        ));
    }

    /**
     * WCAG relative contrast ratio between two hex colors, 1.0 through 21.0.
     */
    public static function contrast(string $first, string $second): float
    {
        $lighter = max(static::luminance($first), static::luminance($second));
        $darker = min(static::luminance($first), static::luminance($second));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    public static function isHex(?string $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-f]{6}$/i', $value) === 1;
    }

    private static function luminance(string $hex): float
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
