<?php

namespace App\Support;

use App\Content\Area;
use App\Content\AreaRepository;
use App\Content\Enums\ContentKind;
use App\Content\Enums\PageTemplate;
use App\Content\Faq;
use App\Content\FaqRepository;
use App\Content\ManagedPage;
use App\Content\Service;
use App\Content\ServiceRepository;

/**
 * JSON-LD generated from the same records that render the pages.
 *
 * Never hand-written per page: markup that disagrees with the visible page is
 * worse than none, and the only reliable way to keep them in step is to build
 * both from one source.
 */
class StructuredData
{
    /**
     * Supply page identity for record-backed pages without duplicating the
     * richer WebPage node supplied by managed content.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    public static function withWebPage(array $nodes, string $url, string $title, ?string $description): array
    {
        foreach ($nodes as $node) {
            if (in_array('WebPage', (array) ($node['@type'] ?? []), true)) {
                return $nodes;
            }
        }

        $nodes[] = array_filter([
            '@type' => 'WebPage',
            '@id' => self::webPageId($url),
            'name' => $title,
            'description' => $description,
            'url' => $url,
            'isPartOf' => ['@id' => self::websiteId()],
            'about' => ['@id' => self::businessId()],
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return $nodes;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function managedPage(ManagedPage $page): array
    {
        $nodes = [array_filter([
            '@type' => 'WebPage',
            '@id' => self::webPageId($page->canonicalUrl()),
            'name' => $page->title,
            'description' => $page->description,
            'url' => $page->canonicalUrl(),
            'isPartOf' => ['@id' => self::websiteId()],
            'about' => ['@id' => self::businessId()],
        ], fn (mixed $value): bool => $value !== null && $value !== '')];

        if ($page->breadcrumbs !== []) {
            $nodes[] = self::breadcrumbs($page->breadcrumbs);
        }

        $faq = self::managedFaq($page);
        if ($faq !== null) {
            $nodes[] = $faq;
        }

        $article = self::managedArticle($page);
        if ($article !== null) {
            $nodes[] = $article;
        }

        // A city page offers plumbing in one service area: the place its
        // location hero names, which is always a service area record.
        $place = self::cityPlace($page);

        if ($place !== null) {
            $nodes[] = [
                '@type' => 'Service',
                'name' => 'Plumbing in '.$place->label(),
                'serviceType' => 'Plumbing',
                'url' => $page->canonicalUrl(),
                'provider' => ['@id' => self::businessId()],
                'areaServed' => [array_filter([
                    '@type' => self::placeType($place->name()),
                    'name' => $place->name(),
                    'containedInPlace' => $place->region() === null ? null : [
                        '@type' => 'AdministrativeArea',
                        'name' => $place->region(),
                    ],
                ])],
            ];

            return $nodes;
        }

        // A service-detail page describes one service whatever its kind, so it
        // says so in the markup too.
        if ($page->kind === ContentKind::Service || $page->template === PageTemplate::ServiceDetail) {
            $nodes[] = array_filter([
                '@type' => 'Service',
                'name' => $page->title,
                'description' => $page->description,
                'url' => $page->canonicalUrl(),
                'provider' => ['@id' => self::businessId()],
            ], fn (mixed $value): bool => $value !== null && $value !== '');
        }

        return $nodes;
    }

    /**
     * The site itself, published by the business. Search engines read the
     * site's name from this node.
     */
    public static function websiteId(): string
    {
        return rtrim(url('/'), '/').'/#website';
    }

    /**
     * The page node's id. The homepage's canonical URL has no path, and
     * `https://example.com#webpage` beside `https://example.com/#business`
     * reads as a different address. Both are the root, so the id says so.
     */
    private static function webPageId(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return ($path === null || $path === '' ? $url.'/' : $url).'#webpage';
    }

    /**
     * @return array<string, mixed>
     */
    public static function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => self::websiteId(),
            'name' => config('site.name'),
            'url' => rtrim(url('/'), '/'),
            'publisher' => ['@id' => self::businessId()],
        ];
    }

    /**
     * A stable identifier for the business node, so every other node can point
     * at it instead of repeating the details.
     *
     * Derived from the request rather than APP_URL so it always matches the
     * host in the URLs alongside it. A graph that references a business at one
     * host from pages on another is not connected at all.
     */
    public static function businessId(): string
    {
        return rtrim(url('/'), '/').'/#business';
    }

    /**
     * The business itself. Emitted on every page.
     *
     * @return array<string, mixed>
     */
    public static function localBusiness(): array
    {
        $areas = app(AreaRepository::class)->all();
        $services = app(ServiceRepository::class)->all();

        $business = array_filter([
            // `?:` rather than a config default: the key ships as null, and a
            // null that reaches array_filter drops the type from the entity.
            '@type' => config('site.business_type') ?: 'LocalBusiness',
            '@id' => self::businessId(),
            'name' => config('site.name'),
            'description' => config('site.description'),
            'url' => rtrim(url('/'), '/'),
            'telephone' => config('site.phone') ?: null,
            'email' => config('site.email') ?: null,
        ]);

        if (filled(config('site.address'))) {
            $business['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => config('site.address'),
            ];
        }

        if ($areas->isNotEmpty()) {
            $business['areaServed'] = $areas->map(fn (Area $area): array => array_filter([
                '@type' => self::placeType($area->name()),
                'name' => $area->name(),
                'containedInPlace' => $area->region() === null ? null : [
                    '@type' => 'AdministrativeArea',
                    'name' => $area->region(),
                ],
            ]))->values()->all();
        }

        if ($services->isNotEmpty()) {
            $business['hasOfferCatalog'] = [
                '@type' => 'OfferCatalog',
                'name' => 'Services',
                'itemListElement' => $services->map(fn (Service $service): array => [
                    '@type' => 'Offer',
                    'itemOffered' => [
                        '@type' => 'Service',
                        'name' => $service->name(),
                        'url' => $service->url(),
                    ],
                ])->all(),
            ];
        }

        if (filled(config('site.social_links'))) {
            $business['sameAs'] = array_column(config('site.social_links'), 'url');
        }

        if (($hours = self::openingHours()) !== []) {
            $business['openingHoursSpecification'] = $hours;
        }

        if (($people = self::people()) !== []) {
            $business['employee'] = $people;
        }

        /*
         * An entity with no picture is harder to attach to a Knowledge Panel or
         * to cite in an AI answer, both of which want something to show. `logo`
         * is the mark itself; `image` is a photograph of the business, which
         * the logo never stands in for.
         */
        if ($logo = SiteLogo::forSchema()) {
            $business['logo'] = [
                '@type' => 'ImageObject',
                'url' => $logo['url'],
                'width' => $logo['width'],
                'height' => $logo['height'],
            ];
        }

        if ($photo = self::photo()) {
            $business['image'] = $photo;
        }

        return $business;
    }

    /**
     * The site's photograph of the business, from site settings. Declared
     * only while the file exists, so a renamed image can't leave a broken URL
     * in the markup of every page.
     *
     * @return array<string, mixed>|null
     */
    private static function photo(): ?array
    {
        $photo = config('site.photo');

        if (! is_array($photo)
            || ! is_string($photo['src'] ?? null)
            || trim($photo['src']) === ''
            || ! is_file(public_path($photo['src']))) {
            return null;
        }

        return array_filter([
            '@type' => 'ImageObject',
            'url' => asset($photo['src']),
            'width' => is_int($photo['width'] ?? null) && $photo['width'] > 0 ? $photo['width'] : null,
            'height' => is_int($photo['height'] ?? null) && $photo['height'] > 0 ? $photo['height'] : null,
        ]);
    }

    /**
     * One service, optionally narrowed to a single area.
     *
     * @return array<string, mixed>
     */
    public static function service(Service $service, ?Area $area = null): array
    {
        $areas = $area !== null
            ? collect([$area])
            : app(AreaRepository::class)->all()->filter(
                fn (Area $candidate): bool => $service->servesArea($candidate)
            );

        return array_filter([
            '@type' => 'Service',
            'name' => $area === null
                ? $service->name()
                : $service->name().' in '.$area->name(),
            'description' => $service->summary(),
            'serviceType' => $service->name(),
            'url' => $area === null ? $service->url() : $service->urlInArea($area),
            'provider' => ['@id' => self::businessId()],
            'areaServed' => $areas->isEmpty() ? null : $areas->map(
                fn (Area $candidate): array => ['@type' => self::placeType($candidate->name()), 'name' => $candidate->name()]
            )->values()->all(),
        ]);
    }

    /**
     * The service area a city page is about, from its location hero.
     */
    private static function cityPlace(ManagedPage $page): ?Area
    {
        if ($page->template !== PageTemplate::City) {
            return null;
        }

        $hero = collect($page->blocks)->first(fn ($block): bool => $block->type === 'hero/location');
        $name = is_string($hero?->props['place'] ?? null) ? trim($hero->props['place']) : '';

        if ($name === '') {
            return null;
        }

        return app(AreaRepository::class)->all()->first(
            fn (Area $area): bool => strcasecmp($area->name(), $name) === 0
                || strcasecmp($area->label(), $name) === 0,
        );
    }

    /**
     * A county is not a city. Service-area lists mix the two, and calling
     * "Utah County" a City tells a search engine to look for a town that
     * doesn't exist.
     */
    private static function placeType(string $name): string
    {
        return preg_match('/\b(County|Parish|Borough)$/i', trim($name)) === 1
            ? 'AdministrativeArea'
            : 'City';
    }

    /**
     * Opening hours from site settings, for example a 24/7 business that
     * lists every day from 00:00 to 23:59.
     *
     * @return list<array<string, mixed>>
     */
    private static function openingHours(): array
    {
        return collect(config('site.opening_hours') ?: [])
            ->filter(fn (mixed $hours): bool => is_array($hours)
                && is_array($hours['days'] ?? null)
                && $hours['days'] !== []
                && is_string($hours['opens'] ?? null)
                && is_string($hours['closes'] ?? null))
            ->map(fn (array $hours): array => [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => array_values($hours['days']),
                'opens' => $hours['opens'],
                'closes' => $hours['closes'],
            ])
            ->values()
            ->all();
    }

    /**
     * The people the business names on its own pages, such as its owner.
     *
     * @return list<array<string, mixed>>
     */
    private static function people(): array
    {
        return collect(config('site.people') ?: [])
            ->filter(fn (mixed $person): bool => is_array($person)
                && is_string($person['name'] ?? null)
                && trim($person['name']) !== '')
            ->map(fn (array $person): array => array_filter([
                '@type' => 'Person',
                'name' => trim($person['name']),
                'jobTitle' => is_string($person['job_title'] ?? null) ? $person['job_title'] : null,
                'url' => is_string($person['url'] ?? null) && str_starts_with($person['url'], '/')
                    ? url($person['url'])
                    : null,
            ]))
            ->values()
            ->all();
    }

    /**
     * The questions shown on this page.
     *
     * Only emitted when questions are actually rendered: markup describing
     * content a visitor cannot see is the kind of thing search engines treat
     * as a manual action rather than a ranking signal.
     *
     * @return array<string, mixed>|null
     */
    public static function faqPage(?Service $service = null, ?Area $area = null): ?array
    {
        $faqs = app(FaqRepository::class)->for($service, $area);

        if ($faqs->isEmpty()) {
            return null;
        }

        return [
            '@type' => 'FAQPage',
            'mainEntity' => $faqs->map(fn (Faq $faq): array => [
                '@type' => 'Question',
                'name' => $faq->question(),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq->answer(),
                ],
            ])->all(),
        ];
    }

    /**
     * @param  array<int, array{name: string, url: string}>  $crumbs
     * @return array<string, mixed>
     */
    public static function breadcrumbs(array $crumbs): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($crumbs)->values()->map(fn (array $crumb, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ])->all(),
        ];
    }

    /**
     * The page's JSON-LD, one document per node, business first. The business
     * is declared once and the rest point at it by `@id`.
     *
     * Each node is its own document rather than one `@graph`. Search engines
     * read either, and the `@id` references join the nodes across documents,
     * but simpler readers (AI crawlers and readiness checkers among them) only
     * look at each document's top-level type and miss everything in a graph.
     *
     * @param  array<int, array<string, mixed>|null>  $nodes
     * @return list<array<string, mixed>>
     */
    public static function documents(array $nodes = []): array
    {
        return array_map(
            fn (array $node): array => ['@context' => 'https://schema.org', ...$node],
            [self::localBusiness(), self::website(), ...array_values(array_filter($nodes))],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function managedFaq(ManagedPage $page): ?array
    {
        $questions = collect($page->blocks)
            ->filter(fn ($block): bool => in_array($block->type, ['faq/accordion', 'faq/highlighted'], true))
            ->flatMap(fn ($block): array => is_array($block->props['items'] ?? null)
                ? $block->props['items']
                : [])
            // An accordion can hold labelled reasons ("Expertise", "Safety")
            // as well as questions. Only an item phrased as a question is one,
            // and marking a label up as a question misdescribes the page.
            ->filter(fn (mixed $item): bool => is_array($item)
                && is_string($item['question'] ?? null)
                && str_ends_with(trim($item['question']), '?')
                && is_string($item['answer'] ?? null)
                && trim($item['answer']) !== '')
            ->values();

        if ($questions->isEmpty()) {
            return null;
        }

        return [
            '@type' => 'FAQPage',
            'mainEntity' => $questions->map(fn (array $item): array => [
                '@type' => 'Question',
                'name' => trim($item['question']),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => trim($item['answer']),
                ],
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function managedArticle(ManagedPage $page): ?array
    {
        if ($page->kind !== ContentKind::Article) {
            return null;
        }

        $hero = collect($page->blocks)->first(
            fn ($block): bool => $block->type === 'hero/article',
        );

        if ($hero === null
            || ! is_string($hero->props['author'] ?? null)
            || ! is_string($hero->props['publishedAt'] ?? null)) {
            return null;
        }

        return array_filter([
            '@type' => 'Article',
            'headline' => $hero->props['heading'] ?? $page->title,
            'description' => $hero->props['summary'] ?? $page->description,
            'datePublished' => $hero->props['publishedAt'],
            // Emitted only when the article records one. An article rewritten
            // years after publication otherwise presents itself as untouched.
            'dateModified' => is_string($hero->props['updatedAt'] ?? null)
                ? $hero->props['updatedAt']
                : null,
            /*
             * An article credited to the business is written by the business,
             * not by a person who happens to share its name. Declaring a
             * `Person` named after the business is the kind of markup that
             * contradicts the page it describes, and the byline is the field
             * Google reads hardest for article authorship.
             */
            'author' => $hero->props['author'] === config('site.name')
                ? ['@id' => self::businessId()]
                : [
                    '@type' => 'Person',
                    'name' => $hero->props['author'],
                ],
            'reviewedBy' => is_array($hero->props['reviewer'] ?? null)
                && is_string($hero->props['reviewer']['name'] ?? null)
                    ? [
                        '@type' => 'Person',
                        'name' => $hero->props['reviewer']['name'],
                    ]
                    : null,
            'publisher' => ['@id' => self::businessId()],
            // Article results want an image; the page's share image is the
            // photo it already presents as itself.
            'image' => is_string($page->socialImage['src'] ?? null)
                ? (str_starts_with($page->socialImage['src'], '/') ? url($page->socialImage['src']) : $page->socialImage['src'])
                : null,
            'mainEntityOfPage' => $page->canonicalUrl(),
        ]);
    }
}
