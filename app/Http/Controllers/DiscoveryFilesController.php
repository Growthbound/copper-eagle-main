<?php

namespace App\Http\Controllers;

use App\Content\AreaRepository;
use App\Content\Enums\ContentKind;
use App\Content\ManagedContentRepository;
use App\Content\Page;
use App\Content\PageRepository;
use App\Content\ServiceRepository;
use App\Content\UrlDispositionRegistry;
use App\Support\SearchVisibility;
use Illuminate\Http\Response;

class DiscoveryFilesController extends Controller
{
    public function robots(): Response
    {
        /*
         * A non-canonical host has to be crawlable to stop being indexed.
         *
         * Blocking search engines outright looks decisive and does the opposite
         * of what it promises: a crawler that cannot fetch the page never sees
         * the `noindex` on it, so URLs it already holds sit in the index as
         * "indexed, though blocked by robots.txt" indefinitely. Letting it in
         * to read `noindex` — served here as a header on every response, so it
         * also covers the sitemap and the images — is what actually removes
         * them.
         *
         * Answer-engine crawlers are refused, because a training corpus has no
         * retraction: the only useful moment to say no is before the fetch. The
         * sitemap is still not advertised, since nothing here wants discovery.
         */
        if (! SearchVisibility::indexable()) {
            $lines = [];

            foreach (config('search.answer_engine_crawlers', []) as $crawler) {
                $lines[] = 'User-agent: '.$crawler;
                $lines[] = 'Disallow: /';
                $lines[] = '';
            }

            $lines[] = 'User-agent: *';
            $lines[] = 'Allow: /';
            $lines[] = '';

            return response(implode("\n", $lines), 200)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $baseUrl = rtrim((string) config('app.url'), '/');
        $body = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /api',
            'Disallow: /_templates',
            'Disallow: /templates',
            '',
            'Sitemap: '.$baseUrl.'/sitemap.xml',
            '',
        ]);

        return response($body, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function llms(ManagedContentRepository $managedPages): Response
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $lines = ['# '.config('site.name'), ''];

        // A new install has no description yet, and an empty quote reads as a
        // summary that says nothing.
        if (filled(config('site.description'))) {
            $lines[] = '> '.config('site.description');
            $lines[] = '';
        }

        $lines[] = 'Public homepage: '.$baseUrl;

        if (filled(config('site.email'))) {
            $lines[] = 'Email: '.config('site.email');
        }

        if (filled(config('site.phone'))) {
            $lines[] = 'Phone: '.config('site.phone');
        }

        if (filled(config('site.address'))) {
            $lines[] = 'Address: '.config('site.address');
        }

        $serviceAreas = app(AreaRepository::class)->all()
            ->map(fn ($area): string => $area->label())
            ->values()
            ->all();

        if ($serviceAreas !== []) {
            $lines[] = 'Service areas: '.implode(', ', $serviceAreas);
        }

        $publicManaged = $managedPages->publishedPages()
            ->reject(fn ($page): bool => $page->noindex);

        if ($publicManaged->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '## Published pages';
            foreach ($publicManaged as $page) {
                $lines[] = '- '.$page->title.': '.$page->url();
            }
        }

        return response(implode("\n", $lines)."\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /**
     * Built from the pages that actually exist, so adding a page file publishes
     * it to search engines without anyone remembering to update a list.
     * Pages marked noindex are left out.
     */
    public function sitemap(
        PageRepository $pages,
        ServiceRepository $services,
        AreaRepository $areas,
        ManagedContentRepository $managedPages,
        UrlDispositionRegistry $dispositions,
    ): Response {
        $baseUrl = rtrim((string) config('app.url'), '/');

        $managed = $managedPages->publishedPages();
        $managedSlugs = $managedPages->claimedPageSlugs()->all();
        $urls = $pages->all()
            ->reject(fn (Page $page): bool => $page->noindex || in_array($page->slug, $managedSlugs, true))
            ->mapWithKeys(fn (Page $page): array => [$page->slug => $page->slug === '/'
                ? $baseUrl
                : $baseUrl.'/'.ltrim($page->slug, '/')]);

        foreach ($managed->reject(fn ($page): bool => $page->noindex) as $page) {
            $urls[$page->slug] = $page->canonicalUrl();
        }

        if ($managed->contains(fn ($page): bool => $page->kind === ContentKind::Article && ! $page->noindex)) {
            $urls['blog'] = $baseUrl.'/blog/';
        }

        // Everything generated from records, so publishing a service or an area
        // is the single act of adding its file.
        if ($services->isEmpty() && $areas->isEmpty()) {
            return $this->render($urls->values()->all());
        }

        $generated = collect(['/services', '/areas']);

        foreach ($services->all() as $service) {
            $generated->push('/services/'.$service->slug);
        }

        foreach ($areas->all() as $area) {
            $generated->push('/areas/'.$area->slug);
        }

        /*
         * Records whose ledger disposition folds them into a pillar still have
         * a file, so they still generate a path here. Submitting a URL that
         * answers 301 asks Google to crawl the duplicate in order to be told
         * to ignore it, which is the opposite of consolidating.
         */
        $generated = $generated->reject(
            fn (string $path): bool => $dispositions->isForwarded($path),
        );

        $urls = [
            ...$urls->values()->all(),
            ...$generated->map(fn (string $path): string => $baseUrl.$path)->all(),
        ];

        return $this->render(array_values(array_unique($urls)));
    }

    /**
     * @param  array<int, string>  $urls
     */
    private function render(array $urls): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= view('discovery.sitemap', [
            'urls' => $urls === [] ? [rtrim((string) config('app.url'), '/')] : $urls,
        ])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
