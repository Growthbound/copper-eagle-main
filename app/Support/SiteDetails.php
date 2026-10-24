<?php

namespace App\Support;

use App\Enums\MediaKind;
use App\Models\Setting;
use Illuminate\Validation\Rule;

/**
 * Owner-editable business details.
 *
 * config/site.php holds the shipped defaults; anything saved here overrides
 * them at runtime, so the footer, contact links, and llms.txt reflect what the
 * owner typed without a redeploy.
 *
 * Business identity only. Anything that generates a page — services, service
 * areas — is a record in content/ and belongs to whoever maintains the
 * repository, so there is one answer to who edits what.
 */
class SiteDetails
{
    public const PREFIX = 'site.';

    /**
     * Editable fields, in the order the admin form shows them.
     *
     * @return array<string, array{label: string, help: string, type: string, rules: array<int, mixed>}>
     */
    public static function fields(): array
    {
        return [
            'name' => [
                'label' => 'Business name',
                'help' => 'Used in the header, footer, browser title, structured data, and transactional messages.',
                'type' => 'text',
                'rules' => ['required', 'string', 'max:120'],
            ],
            'logo_media_id' => [
                'label' => 'Brand logo',
                'help' => 'Choose an image from the media library, or use the business name as a text wordmark.',
                'type' => 'media',
                'rules' => [
                    'nullable',
                    'integer',
                    Rule::exists('media', 'id')
                        ->where('kind', MediaKind::Image->value),
                ],
            ],
            'phone' => [
                'label' => 'Phone number',
                'help' => 'Shown in the footer as a tap-to-call link. Leave blank to hide it.',
                'type' => 'text',
                'rules' => ['nullable', 'string', 'max:40'],
            ],
            'email' => [
                'label' => 'Email address',
                'help' => 'Shown in the footer as a mailto link. Leave blank to hide it.',
                'type' => 'text',
                'rules' => ['nullable', 'email', 'max:190'],
            ],
            'address' => [
                'label' => 'Address',
                'help' => 'Optional. Shown in the footer contact block.',
                'type' => 'text',
                'rules' => ['nullable', 'string', 'max:190'],
            ],
            'description' => [
                'label' => 'Site description',
                'help' => 'One sentence used in the footer and as the page meta description.',
                'type' => 'textarea',
                'rules' => ['nullable', 'string', 'max:300'],
            ],
        ];
    }

    /**
     * Stored values keyed by field, ready to merge over config('site').
     *
     * @return array<string, string|array<int, string>>
     */
    public static function overrides(): array
    {
        $saved = Setting::values();

        $overrides = [];

        foreach (self::fields() as $field => $definition) {
            $value = $saved[self::PREFIX.$field] ?? null;

            if ($value === null || trim($value) === '') {
                continue;
            }

            $overrides[$field] = match ($definition['type']) {
                'lines' => self::toList($value),
                'media' => (int) $value,
                default => $value,
            };
        }

        return $overrides;
    }

    /**
     * Current values in the shape the admin form edits, with list fields
     * flattened back to one entry per line.
     *
     * @return array<string, string>
     */
    public static function formValues(): array
    {
        $values = [];

        foreach (self::fields() as $field => $definition) {
            $current = config('site.'.$field);

            $values[$field] = $definition['type'] === 'lines'
                ? implode("\n", (array) $current)
                : (string) $current;
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $values
     */
    public static function save(array $values): void
    {
        $stored = [];

        foreach (self::fields() as $field => $definition) {
            $value = trim((string) ($values[$field] ?? ''));

            $stored[self::PREFIX.$field] = $value === '' ? null : $value;
        }

        Setting::write($stored);
    }

    /**
     * @return array<int, string>
     */
    private static function toList(string $value): array
    {
        return array_values(array_filter(
            array_map(trim(...), preg_split('/\r\n|\r|\n/', $value) ?: []),
            fn (string $line): bool => $line !== '',
        ));
    }
}
