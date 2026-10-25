<?php

namespace App\Content;

use App\Enums\MediaKind;
use App\Models\Media;
use Illuminate\Support\Facades\Schema;
use Throwable;

final readonly class BlockData
{
    public function __construct(private RichContent $richContent) {}

    /**
     * @return array<int, string>
     */
    public function strings(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->map(fn (string $item): string => trim($item))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function link(mixed $link, string $labelKey = 'label'): ?array
    {
        if (! is_array($link)
            || ! is_string($link[$labelKey] ?? null)
            || trim($link[$labelKey]) === ''
            || ! $this->richContent->isSafeUrl($link['href'] ?? null)) {
            return null;
        }

        return [
            ...$link,
            $labelKey => trim($link[$labelKey]),
            'href' => $link['href'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function media(mixed $media, bool $requireSource = false): ?array
    {
        if (! is_array($media)) {
            return null;
        }

        $record = is_int($media['media_id'] ?? null)
            && $media['media_id'] > 0
            && $this->mediaCatalogAvailable()
            ? Media::query()->find($media['media_id'])
            : null;

        if ($record !== null && $record->kind !== MediaKind::Image) {
            return null;
        }

        $src = $record?->url() ?? ($media['src'] ?? null);
        $alt = $media['alt'] ?? $record?->alt;
        $width = $record !== null ? $record->width : ($media['width'] ?? null);
        $height = $record !== null ? $record->height : ($media['height'] ?? null);

        if (! $this->richContent->isSafeMediaUrl($src)
            || ! is_string($alt)
            || trim($alt) === ''
            || ! is_int($width)
            || $width < 1
            || ! is_int($height)
            || $height < 1) {
            return null;
        }

        $source = $record === null
            ? $this->source($media)
            : (
                is_string($record->source_url)
                && $this->richContent->isSafeUrl($record->source_url)
                    ? [
                        'label' => $record->title ?: $record->original_name,
                        'url' => $record->source_url,
                    ]
                    : null
            );

        if ($requireSource && $source === null) {
            return null;
        }

        return array_filter([
            'src' => $src,
            'alt' => trim($alt),
            'width' => $width,
            'height' => $height,
            'caption' => is_string($media['caption'] ?? null) && trim($media['caption']) !== ''
                ? trim($media['caption'])
                : null,
            'source_label' => $source['label'] ?? null,
            'source_url' => $source['url'] ?? null,
        ], fn (mixed $value): bool => $value !== null);
    }

    private function mediaCatalogAvailable(): bool
    {
        try {
            return Schema::hasTable('media');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * A decorative background video: a safe .mp4 or .webm source and a poster
     * still with its intrinsic size. The poster needs no alt text because the
     * video is hidden from assistive technology; the section's heading and
     * photo carry the meaning.
     *
     * @return array{src: string, type: string, poster: array{src: string, width: int, height: int}}|null
     */
    public function backgroundVideo(mixed $video): ?array
    {
        if (! is_array($video)
            || ! $this->richContent->isSafeMediaUrl($video['src'] ?? null)
            || ! is_array($video['poster'] ?? null)) {
            return null;
        }

        $poster = $video['poster'];
        $path = strtolower((string) parse_url($video['src'], PHP_URL_PATH));
        $type = match (true) {
            str_ends_with($path, '.mp4') => 'video/mp4',
            str_ends_with($path, '.webm') => 'video/webm',
            default => null,
        };

        if ($type === null
            || ! $this->richContent->isSafeMediaUrl($poster['src'] ?? null)
            || ! is_int($poster['width'] ?? null)
            || $poster['width'] < 1
            || ! is_int($poster['height'] ?? null)
            || $poster['height'] < 1) {
            return null;
        }

        return [
            'src' => $video['src'],
            'type' => $type,
            'poster' => [
                'src' => $poster['src'],
                'width' => $poster['width'],
                'height' => $poster['height'],
            ],
        ];
    }

    /**
     * An absolute http(s) address on another site, for links that open in a
     * new tab (social profiles and posts).
     */
    public function externalUrl(mixed $url): ?string
    {
        if (! is_string($url)
            || filter_var($url, FILTER_VALIDATE_URL) === false
            || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
            || ! $this->richContent->isSafeUrl($url)) {
            return null;
        }

        return $url;
    }

    /**
     * @return array{label: string, url: string}|null
     */
    public function source(mixed $item): ?array
    {
        if (! is_array($item)
            || ! is_string($item['source_label'] ?? null)
            || trim($item['source_label']) === ''
            || ! $this->richContent->isSafeUrl($item['source_url'] ?? null)) {
            return null;
        }

        return [
            'label' => trim($item['source_label']),
            'url' => $item['source_url'],
        ];
    }

    public function date(mixed $date): ?string
    {
        if (! is_string($date)
            || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return checkdate($month, $day, $year) ? $date : null;
    }

    public function anchor(mixed $href): ?string
    {
        if (! is_string($href)
            || preg_match('/^#[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $href) !== 1) {
            return null;
        }

        return $href;
    }

    public function serviceAreaName(mixed $name): ?string
    {
        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        $name = trim($name);

        if (preg_match('/\d/', $name) === 1
            || preg_match('/\b(?:street|st|avenue|ave|road|rd|drive|dr|boulevard|blvd|suite|unit)\b/i', $name) === 1) {
            return null;
        }

        return $name;
    }

    /**
     * @return array<int, array{name: string, href: string, note?: string}>
     */
    public function serviceAreas(mixed $areas): array
    {
        if (! is_array($areas)) {
            return [];
        }

        return collect($areas)
            ->map(function (mixed $area): ?array {
                if (! is_array($area)
                    || ($name = $this->serviceAreaName($area['name'] ?? null)) === null
                    || $this->link(['label' => $name, 'href' => $area['href'] ?? null]) === null) {
                    return null;
                }

                return array_filter([
                    'name' => $name,
                    'href' => $area['href'],
                    'note' => is_string($area['note'] ?? null) && trim($area['note']) !== ''
                        ? trim($area['note'])
                        : null,
                ], fn (mixed $value): bool => $value !== null);
            })
            ->filter()
            ->values()
            ->all();
    }

    public function icon(mixed $name, string $fallback = 'information-circle'): string
    {
        return is_string($name) && array_key_exists($name, config('marketing-icons.icons', []))
            ? $name
            : $fallback;
    }

    /**
     * The single place a block turns an image reference into a URL.
     *
     * Library paths ("/media/43") resolve against MEDIA_BASE_URL so the same
     * document renders against a local server, the dev site, or production
     * without being edited. Everything else is left where it was.
     */
    public function assetUrl(string $src): string
    {
        if (str_starts_with($src, 'http')) {
            return $src;
        }

        if (str_starts_with($src, '/media/')) {
            $base = (string) config('media.base_url');

            // With no external library configured the image belongs to this
            // install, so it is served from here.
            return $base === '' ? asset($src) : MediaIndex::resolve($src, $base);
        }

        if (str_starts_with($src, '/')) {
            return $src;
        }

        return asset($src);
    }
}
