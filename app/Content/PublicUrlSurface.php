<?php

namespace App\Content;

use Illuminate\Support\Collection;

/**
 * Every path the site publishes today, collected from the systems that actually
 * generate them.
 *
 * The site grows URLs from independent places — page files, managed documents,
 * service records, and area records — and nothing else compares them against
 * each other. Adding an area record publishes a page, a sitemap entry, and a
 * schema `areaServed` value in one edit, with no place that has to acknowledge
 * it. This class is what the URL/intent ledger is checked against, so a new
 * page cannot appear without a decision recorded next to it.
 *
 * Paths are normalized to a leading and trailing slash so the sources can be
 * compared at all; `/` stays `/`.
 */
final class PublicUrlSurface
{
    public function __construct(
        private readonly ManagedContentRepository $managed,
        private readonly ServiceRepository $services,
        private readonly AreaRepository $areas,
        private readonly PageRepository $pages,
        private readonly UrlDispositionRegistry $dispositions,
    ) {}

    /**
     * @return Collection<int, array{url: string, source: string, content_kind: string, indexation: string}>
     */
    public function all(): Collection
    {
        $entries = collect();
        $claimed = $this->managed->claimedPageSlugs()->all();

        foreach ($this->pages->all() as $page) {
            if (in_array($page->slug, $claimed, true)) {
                continue;
            }

            $entries->push($this->entry($page->slug, 'page_file', 'page', $page->noindex));
        }

        foreach ($this->managed->publishedPages() as $page) {
            $entries->push($this->entry($page->slug, 'managed', $page->kind->value, $page->noindex));
        }

        foreach ($this->services->all() as $service) {
            $entries->push($this->entry('services/'.$service->slug, 'service_record', 'service', false));
        }

        foreach ($this->areas->all() as $area) {
            $entries->push($this->entry('areas/'.$area->slug, 'area_record', 'area', false));
        }

        /*
         * A path the ledger has folded still answers, but it answers with a
         * 301 rather than a page. Leaving it here would keep it counted as
         * published and indexable, which is the drift this class exists to
         * catch — and would put it back in the sitemap it was just removed
         * from.
         */
        return $entries
            ->reject(fn (array $entry): bool => $this->dispositions->isForwarded($entry['url']))
            ->sortBy('url')
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    public function urls(): Collection
    {
        return $this->all()->pluck('url');
    }

    public static function normalize(string $path): string
    {
        $trimmed = trim($path, '/');

        return $trimmed === '' ? '/' : '/'.$trimmed.'/';
    }

    /**
     * @return array{url: string, source: string, content_kind: string, indexation: string}
     */
    private function entry(string $slug, string $source, string $kind, bool $noindex): array
    {
        return [
            'url' => self::normalize($slug),
            'source' => $source,
            'content_kind' => $kind,
            'indexation' => $noindex ? 'noindex' : 'indexable',
        ];
    }
}
