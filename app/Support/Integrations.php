<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Tracking IDs and third-party widget snippets.
 *
 * Stored in settings rather than environment variables so the owner can change
 * them without a redeploy, and so they persist on the data volume across image
 * rebuilds.
 */
class Integrations
{
    public const PREFIX = 'integrations.';

    /**
     * All configured values, keyed by field, omitting anything blank.
     *
     * @return array<string, string>
     */
    public static function values(): array
    {
        $saved = Setting::values();

        $values = [];

        foreach (array_keys(self::editable()) as $field) {
            $value = trim((string) ($saved[self::PREFIX.$field] ?? ''));

            if ($value !== '') {
                $values[$field] = $value;
            }
        }

        return $values;
    }

    public static function get(string $field): ?string
    {
        return self::values()[$field] ?? null;
    }

    /**
     * Every editable field, ID fields first then the raw snippets.
     *
     * @return array<string, array<string, string>>
     */
    public static function editable(): array
    {
        return config('integrations.fields') + config('integrations.snippets');
    }

    /**
     * @param  array<string, string>  $values
     */
    public static function save(array $values): void
    {
        $stored = [];

        foreach (array_keys(self::editable()) as $field) {
            $value = trim((string) ($values[$field] ?? ''));

            $stored[self::PREFIX.$field] = $value === '' ? null : $value;
        }

        Setting::write($stored);
    }

    /**
     * Whether any third-party script will be injected at all.
     */
    public static function anyConfigured(): bool
    {
        return self::values() !== [];
    }
}
