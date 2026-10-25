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

    /**
     * The page at one address.
     *
     * Only the files that claim the address are validated. Validating a page
     * checks every link on it, so validating the whole site to serve one page
     * made each request pay for every other page as well.
     */
    public function find(string $slug): ?ManagedPage
    {
        $slug = $this->normalize($slug);

        foreach ($this->documents() as $document) {
            if (($document['identity']['slug'] ?? null) !== $slug) {
                continue;
            }

            $page = $this->page($document);

            if ($page !== null) {
                return $page;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, ManagedPage>
     */
    public function all(): Collection
    {
        return $this->documents()
            ->map(fn (array $document): ?ManagedPage => $this->page($document))
            ->filter()
            ->sortBy(fn (ManagedPage $page): string => $page->slug)
            ->values();
    }

    /**
     * Every published page file, decoded but not yet validated.
     *
     * @return Collection<int, array{identity: array<string, mixed>, payload: array<string, mixed>}>
     */
    private function documents(): Collection
    {
        $directory = $this->directory ?? base_path('content/managed');

        if (! File::isDirectory($directory)) {
            return collect();
        }

        return collect(File::allFiles($directory))
            ->filter(fn ($file): bool => $file->getExtension() === 'json')
            ->map(function ($file): ?array {
                try {
                    $document = json_decode(File::get($file->getPathname()), true, flags: JSON_THROW_ON_ERROR);
                } catch (Throwable) {
                    return null;
                }

                if (! is_array($document)
                    || ($document['publication_status'] ?? 'published') !== 'published') {
                    return null;
                }

                return [
                    'identity' => is_array($document['identity'] ?? null) ? $document['identity'] : [],
                    'payload' => is_array($document['payload'] ?? null) ? $document['payload'] : [],
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @param  array{identity: array<string, mixed>, payload: array<string, mixed>}  $document
     */
    private function page(array $document): ?ManagedPage
    {
        try {
            if ($this->validator->validate($document['identity'], $document['payload'])->hasErrors()) {
                return null;
            }

            return ManagedPage::fromDocument($document['identity'], $document['payload']);
        } catch (Throwable) {
            return null;
        }
    }

    private function normalize(string $slug): string
    {
        $slug = trim($slug, '/');

        return $slug === '' ? '/' : $slug;
    }
}
