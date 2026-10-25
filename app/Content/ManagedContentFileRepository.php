<?php

namespace App\Content;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Throwable;

final readonly class ManagedContentFileRepository
{
    /**
     * @param  string|null  $directory  Override for tests; defaults to content/managed.
     */
    public function __construct(
        private ContentValidator $validator,
        private ?string $directory = null,
    ) {}

    public function find(string $slug): ?ManagedPage
    {
        return $this->all()->first(
            fn (ManagedPage $page): bool => $page->slug === $this->normalize($slug),
        );
    }

    /**
     * @return Collection<int, ManagedPage>
     */
    public function all(): Collection
    {
        $directory = $this->directory ?? base_path('content/managed');

        if (! File::isDirectory($directory)) {
            return collect();
        }

        return collect(File::allFiles($directory))
            ->filter(fn ($file): bool => $file->getExtension() === 'json')
            ->map(function ($file): ?ManagedPage {
                try {
                    $document = json_decode(File::get($file->getPathname()), true, flags: JSON_THROW_ON_ERROR);

                    if (($document['publication_status'] ?? 'published') !== 'published') {
                        return null;
                    }

                    $identity = is_array($document['identity'] ?? null) ? $document['identity'] : [];
                    $payload = is_array($document['payload'] ?? null) ? $document['payload'] : [];

                    if ($this->validator->validate($identity, $payload)->hasErrors()) {
                        return null;
                    }

                    return ManagedPage::fromDocument($identity, $payload);
                } catch (Throwable) {
                    return null;
                }
            })
            ->filter()
            ->sortBy(fn (ManagedPage $page): string => $page->slug)
            ->values();
    }

    private function normalize(string $slug): string
    {
        $slug = trim($slug, '/');

        return $slug === '' ? '/' : $slug;
    }
}
