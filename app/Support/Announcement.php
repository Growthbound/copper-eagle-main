<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

/**
 * The site-wide announcement bar.
 *
 * Content and schedule live in settings; the visual treatment is a Blade
 * variant in resources/views/components/blocks/announcement. Adding a template
 * there is all it takes to offer a new style — nothing here needs editing.
 */
class Announcement
{
    public const PREFIX = 'announcement.';

    public const DEFAULT_VARIANT = 'bar';

    /**
     * The announcement to render right now, or null when nothing should show.
     *
     * @return array{message: string, url: string|null, linkLabel: string|null, variant: string}|null
     */
    public static function current(): ?array
    {
        $saved = Setting::values();

        if (($saved[self::PREFIX.'enabled'] ?? '0') !== '1') {
            return null;
        }

        $message = trim((string) ($saved[self::PREFIX.'message'] ?? ''));

        if ($message === '') {
            return null;
        }

        if (! self::withinSchedule(
            $saved[self::PREFIX.'starts_at'] ?? null,
            $saved[self::PREFIX.'ends_at'] ?? null,
        )) {
            return null;
        }

        $url = trim((string) ($saved[self::PREFIX.'url'] ?? ''));
        $linkLabel = trim((string) ($saved[self::PREFIX.'link_label'] ?? ''));

        return [
            'message' => $message,
            'url' => $url ?: null,
            'linkLabel' => $linkLabel ?: null,
            'variant' => self::resolveVariant($saved[self::PREFIX.'variant'] ?? null),
        ];
    }

    /**
     * Every template available in the announcement block folder.
     *
     * @return array<int, string>
     */
    public static function variants(): array
    {
        $directory = resource_path('views/components/blocks/announcement');

        if (! File::isDirectory($directory)) {
            return [self::DEFAULT_VARIANT];
        }

        $variants = collect(File::files($directory))
            ->filter(fn ($file): bool => str_ends_with($file->getFilename(), '.blade.php'))
            ->map(fn ($file): string => str_replace('.blade.php', '', $file->getFilename()))
            ->sort()
            ->values()
            ->all();

        return $variants === [] ? [self::DEFAULT_VARIANT] : $variants;
    }

    /**
     * Falls back to a template that exists, so a deleted variant still renders.
     */
    public static function resolveVariant(?string $variant): string
    {
        $available = self::variants();

        if ($variant !== null && in_array($variant, $available, true)) {
            return $variant;
        }

        return in_array(self::DEFAULT_VARIANT, $available, true)
            ? self::DEFAULT_VARIANT
            : $available[0];
    }

    /**
     * Blank bounds mean "no bound", so an announcement with neither runs until
     * it is switched off.
     */
    private static function withinSchedule(?string $startsAt, ?string $endsAt): bool
    {
        $now = CarbonImmutable::now();

        $start = self::parse($startsAt);

        if ($start !== null && $now->lessThan($start)) {
            return false;
        }

        $end = self::parse($endsAt);

        if ($end !== null && $now->greaterThan($end)) {
            return false;
        }

        return true;
    }

    private static function parse(?string $value): ?CarbonImmutable
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
