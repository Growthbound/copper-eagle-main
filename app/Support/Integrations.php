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
        $defaults = config('integrations.defaults') ?: [];
        $fields = config('integrations.fields') ?: [];

        $values = [];

        foreach (array_keys(self::editable()) as $field) {
            $value = trim((string) ($saved[self::PREFIX.$field] ?? ''));

            // Nothing saved: fall back to the deployment's environment value,
            // as long as it has the field's format.
            if ($value === '' && is_string($defaults[$field] ?? null)) {
                $default = trim($defaults[$field]);
                $pattern = $fields[$field]['pattern'] ?? null;

                if ($default !== '' && (! is_string($pattern) || preg_match($pattern, $default) === 1)) {
                    $value = $default;
                }
            }

            if ($value !== '') {
                $values[$field] = $value;
            }
        }

        return $values;
    }

    /**
     * What the public page may load: everything configured on the canonical
     * host, and nothing elsewhere unless tracking is allowed on every host.
     *
     * @return array<string, string>
     */
    public static function forPublicPage(): array
    {
        if (config('integrations.canonical_host_only', true) && ! SearchVisibility::indexable()) {
            return [];
        }

        return self::values();
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
