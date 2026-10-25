<?php

namespace App\Support;

use Illuminate\Support\Str;

final class TemplatePreviewCatalog
{
    public function __construct(private readonly ?string $previewPath = null) {}

    /**
     * @return array<int, array{slug: string, title: string, href: string}>
     */
    public function all(): array
    {
        $path = $this->previewPath ?? resource_path('views/template-previews');

        if (! is_dir($path)) {
            return [];
        }

        return collect(glob($path.'/*.blade.php') ?: [])
            ->map(fn (string $file): string => Str::of($file)
                ->afterLast(DIRECTORY_SEPARATOR)
                ->beforeLast('.blade.php')
                ->toString())
            ->filter(fn (string $slug): bool => $slug !== 'index'
                && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1)
            ->map(fn (string $slug): array => [
                'slug' => $slug,
                'title' => Str::headline($slug),
                'href' => url('/_templates/'.$slug),
            ])
            ->sortBy('title')
            ->values()
            ->all();
    }

    /**
     * @return array{slug: string, title: string, href: string}|null
     */
    public function find(string $slug): ?array
    {
        return collect($this->all())
            ->first(fn (array $preview): bool => $preview['slug'] === $slug);
    }
}
