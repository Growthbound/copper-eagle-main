<?php

namespace App\Content;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Reads pages from content/pages.
 *
 * The file layout is the routing table: home.json serves "/", about.json
 * serves "/about", and services/gutter-cleaning.json serves
 * "/services/gutter-cleaning". There is no separate registry to keep in step.
 */
class PageRepository
{
    public function directory(): string
    {
        return base_path('content/pages');
    }

    public function find(string $slug): ?Page
    {
        $slug = $this->normalize($slug);

        $relative = $slug === '/' ? 'home' : $slug;

        $path = $this->directory().'/'.$relative.'.json';

        if (! $this->isWithinContent($path) || ! File::isFile($path)) {
            return null;
        }

        return Page::fromFile($path, $slug);
    }

    /**
     * Every page on the site, ordered so "/" comes first.
     *
     * @return Collection<int, Page>
     */
    public function all(): Collection
    {
        return $this->slugs()
            ->map(fn (string $slug): ?Page => $this->find($slug))
            ->filter()
            ->sortBy(fn (Page $page): string => $page->slug === '/' ? '' : $page->slug)
            ->values();
    }

    /**
     * Slugs for every page file, without loading or validating them.
     *
     * @return Collection<int, string>
     */
    public function slugs(): Collection
    {
        if (! File::isDirectory($this->directory())) {
            return collect();
        }

        return collect(File::allFiles($this->directory()))
            ->filter(fn ($file): bool => $file->getExtension() === 'json')
            ->map(function ($file): string {
                $relative = str_replace('\\', '/', $file->getRelativePathname());
                $relative = substr($relative, 0, -strlen('.json'));

                return $relative === 'home' ? '/' : $relative;
            })
            ->values();
    }

    private function normalize(string $slug): string
    {
        $slug = trim($slug, '/');

        return $slug === '' ? '/' : $slug;
    }

    /**
     * Guards against a slug climbing out of the content directory with "..".
     */
    private function isWithinContent(string $path): bool
    {
        $directory = realpath($this->directory());
        $resolved = realpath($path);

        return $directory !== false
            && $resolved !== false
            && str_starts_with($resolved, $directory.DIRECTORY_SEPARATOR);
    }
}
