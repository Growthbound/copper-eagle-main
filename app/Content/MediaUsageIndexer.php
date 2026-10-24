<?php

namespace App\Content;

use App\Content\Exceptions\ContentValidationFailed;
use App\Content\Validation\ContentValidationResult;
use App\Enums\MediaKind;
use App\Models\ContentRevision;
use App\Models\Media;
use App\Models\MediaUsage;

final class MediaUsageIndexer
{
    public function index(
        ContentRevision $revision,
        bool $allowUnresolved = false,
    ): void {
        $revision->mediaUsages()->delete();

        foreach ($this->references($revision->payload) as $reference) {
            $media = Media::query()
                ->whereKey($reference['media_id'])
                ->lockForUpdate()
                ->first();

            // A reference the media index describes belongs to the governed
            // library, which may be hosted on another install. There is no
            // local row to attach a usage record to, and the index has already
            // vouched for the asset.
            if (! $media instanceof Media
                && app(MediaIndex::class)->find($reference['media_id']) !== null) {
                continue;
            }

            if (! $media instanceof Media) {
                if ($allowUnresolved) {
                    continue;
                }

                throw new ContentValidationFailed(new ContentValidationResult([
                    $reference['json_pointer'] => ['The referenced media item does not exist.'],
                ]));
            }

            if (! $allowUnresolved
                && $media->kind === MediaKind::Image
                && $this->dependsOnCatalogAlt(
                    $revision->payload,
                    $reference['json_pointer'],
                )
                && (! is_string($media->alt) || trim($media->alt) === '')) {
                throw new ContentValidationFailed(new ContentValidationResult([
                    $this->parentPointer($reference['json_pointer']).'/alt' => [
                        'Add alt text to the media item or this content reference.',
                    ],
                ]));
            }

            MediaUsage::create([
                'media_id' => $reference['media_id'],
                'content_revision_id' => $revision->id,
                'json_pointer' => $reference['json_pointer'],
                'usage_type' => $reference['usage_type'],
            ]);
        }
    }

    /**
     * @return array<int, array{media_id: int, json_pointer: string, usage_type: string}>
     */
    private function references(
        mixed $value,
        string $pointer = '',
        string $usageType = 'media',
    ): array {
        if (! is_array($value)) {
            return [];
        }

        $references = [];

        foreach ($value as $key => $child) {
            $segment = $this->pointerSegment((string) $key);
            $childPointer = $pointer.'/'.$segment;

            if ($key === 'media_id' && is_int($child) && $child > 0) {
                $references[] = [
                    'media_id' => $child,
                    'json_pointer' => $childPointer,
                    'usage_type' => $usageType,
                ];

                continue;
            }

            if (is_string($child) && in_array($key, ['src', 'video_url'], true)) {
                $mediaId = $this->catalogMediaId($child);

                if ($mediaId !== null) {
                    $references[] = [
                        'media_id' => $mediaId,
                        'json_pointer' => $childPointer,
                        'usage_type' => $key === 'video_url' ? 'video' : $usageType,
                    ];
                }
            }

            $nextType = is_string($key)
                && in_array($key, ['image', 'video', 'poster', 'logo', 'gallery'], true)
                    ? $key
                    : $usageType;

            array_push(
                $references,
                ...$this->references($child, $childPointer, $nextType),
            );
        }

        return $references;
    }

    public function catalogMediaId(string $url): ?int
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path)) {
            return null;
        }

        if (! str_starts_with($url, '/')) {
            $urlHost = parse_url($url, PHP_URL_HOST);
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            $urlPort = $this->effectivePort($url);
            $appPort = $this->effectivePort((string) config('app.url'));

            if (! is_string($urlHost)
                || ! is_string($appHost)
                || strcasecmp($urlHost, $appHost) !== 0
                || $urlPort !== $appPort) {
                return null;
            }
        }

        if (preg_match('#^/media/([1-9][0-9]*)/?$#D', rawurldecode($path), $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function effectivePort(string $url): ?int
    {
        $port = parse_url($url, PHP_URL_PORT);

        if (is_int($port)) {
            return $port;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return is_string($scheme)
            ? match (strtolower($scheme)) {
                'http' => 80,
                'https' => 443,
                default => null,
            }
        : null;
    }

    private function pointerSegment(string $segment): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $segment);
    }

    private function dependsOnCatalogAlt(
        mixed $payload,
        string $referencePointer,
    ): bool {
        $parent = $this->valueAtPointer(
            $payload,
            $this->parentPointer($referencePointer),
        );

        return ! is_array($parent)
            || ! is_string($parent['alt'] ?? null)
            || trim($parent['alt']) === '';
    }

    private function parentPointer(string $pointer): string
    {
        $segments = explode('/', trim($pointer, '/'));
        array_pop($segments);

        return $segments === [] ? '' : '/'.implode('/', $segments);
    }

    private function valueAtPointer(mixed $value, string $pointer): mixed
    {
        if ($pointer === '') {
            return $value;
        }

        foreach (explode('/', ltrim($pointer, '/')) as $segment) {
            $key = str_replace(['~1', '~0'], ['/', '~'], $segment);

            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return null;
            }

            $value = $value[$key];
        }

        return $value;
    }
}
