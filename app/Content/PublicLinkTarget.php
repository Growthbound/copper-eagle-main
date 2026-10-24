<?php

namespace App\Content;

use App\Content\Enums\ContentKind;
use App\Models\ContentAuditEvent;
use App\Models\ContentEntry;
use App\Models\Media;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

final readonly class PublicLinkTarget
{
    public function __construct(
        private RichContent $links,
        private PageRepository $pages,
        private ServiceRepository $services,
        private AreaRepository $areas,
    ) {}

    /**
     * @param  array<int, string>  $unavailablePublicPaths
     */
    public function exists(
        mixed $href,
        array $unavailablePublicPaths = [],
    ): bool {
        if (! $this->links->isSafeUrl($href) || ! is_string($href)) {
            return false;
        }

        if ($href === '') {
            return false;
        }

        if ($href[0] !== '/') {
            if (! $this->hasSameOrigin($href, (string) config('app.url'))) {
                return true;
            }

            $localPath = parse_url($href, PHP_URL_PATH);

            if (! is_string($localPath)) {
                return false;
            }

            $href = $localPath === '' ? '/' : $localPath;
        }

        if (str_starts_with($href, '//')) {
            return false;
        }

        $path = $this->normalizePath($href);

        if ($path === null) {
            return false;
        }

        $normalizedUnavailablePaths = array_values(array_filter(array_map(
            fn (mixed $unavailablePath): ?string => is_string($unavailablePath)
                ? $this->normalizePath($unavailablePath)
                : null,
            $unavailablePublicPaths,
        )));

        if (in_array($path, $normalizedUnavailablePaths, true)) {
            return false;
        }

        if (in_array($path, [
            '/services',
            '/areas',
            '/robots.txt',
            '/llms.txt',
            '/sitemap.xml',
        ], true)) {
            return true;
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
        $slug = trim($path, '/') ?: '/';

        if ($this->managedFileExists($slug)) {
            return true;
        }

        if (($segments[0] ?? null) === 'services') {
            if (count($segments) === 2) {
                return $this->services->exists($segments[1]);
            }

            if (count($segments) !== 3) {
                return false;
            }

            if ($this->pages->find(implode('/', $segments)) !== null) {
                return true;
            }

            return false;
        }

        if (($segments[0] ?? null) === 'areas') {
            return count($segments) === 2 && $this->areas->exists($segments[1]);
        }

        if (($segments[0] ?? null) === 'media') {
            return count($segments) === 2
                && ctype_digit($segments[1])
                && Media::query()->whereKey((int) $segments[1])->exists();
        }

        if (! Schema::hasTable('content_entries')) {
            return $this->pages->find($slug) !== null;
        }

        if (ContentEntry::query()
            ->where('kind', ContentKind::Page->value)
            ->where('slug', $slug)
            ->whereNotNull('published_revision_id')
            ->exists()) {
            return true;
        }

        if ($this->wasPublished($slug)) {
            return false;
        }

        return $this->pages->find($slug) !== null;
    }

    private function managedFileExists(string $slug): bool
    {
        if ($slug === '/' || str_contains($slug, '..')) {
            return false;
        }

        $path = base_path('content/managed/'.$slug.'.json');

        if (! File::isFile($path)) {
            return false;
        }

        try {
            $document = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

            return is_array($document)
                && ($document['publication_status'] ?? 'published') === 'published';
        } catch (\Throwable) {
            return false;
        }
    }

    private function normalizePath(string $target): ?string
    {
        $path = parse_url($target, PHP_URL_PATH);

        if (! is_string($path)) {
            return null;
        }

        $path = rawurldecode($path);

        if ($path === ''
            || ! str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || str_contains(substr($path, 1), '//')
            || preg_match('#(?:^|/)\.\.?(?:/|$)#', $path) === 1) {
            return null;
        }

        return rtrim($path, '/') ?: '/';
    }

    private function hasSameOrigin(string $url, string $appUrl): bool
    {
        $target = parse_url($url);
        $application = parse_url($appUrl);

        if (! is_array($target)
            || ! is_array($application)
            || ! is_string($target['scheme'] ?? null)
            || ! is_string($application['scheme'] ?? null)
            || ! is_string($target['host'] ?? null)
            || ! is_string($application['host'] ?? null)) {
            return false;
        }

        return strcasecmp($target['scheme'], $application['scheme']) === 0
            && strcasecmp($target['host'], $application['host']) === 0
            && $this->effectivePort($target) === $this->effectivePort($application);
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private function effectivePort(array $parts): ?int
    {
        if (isset($parts['port']) && is_int($parts['port'])) {
            return $parts['port'];
        }

        return match (strtolower((string) ($parts['scheme'] ?? ''))) {
            'http' => 80,
            'https' => 443,
            default => null,
        };
    }

    private function wasPublished(string $slug): bool
    {
        return ContentAuditEvent::query()
            ->with(['entry:id,kind,slug', 'revision:id,identity'])
            ->whereIn('action', ['publish', 'rollback'])
            ->get()
            ->contains(function (ContentAuditEvent $event) use ($slug): bool {
                $identity = is_array($event->revision?->identity)
                    ? $event->revision->identity
                    : [];
                $kind = $identity['kind'] ?? $event->entry?->kind;
                $kind = $kind instanceof ContentKind ? $kind->value : $kind;
                $historicSlug = $identity['slug'] ?? $event->entry?->slug;

                return $kind === ContentKind::Page->value
                    && is_string($historicSlug)
                    && (trim($historicSlug, '/') ?: '/') === $slug;
            });
    }
}
