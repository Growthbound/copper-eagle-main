<?php

namespace App\Content;

use App\Content\Enums\ContentKind;
use App\Models\ContentEntry;
use Illuminate\Support\Facades\Schema;

final class SiteStructureRepository
{
    /**
     * @return array<int, array{label: string, href: string}>
     */
    public function navigation(bool $lockForUpdate = false): array
    {
        $payload = $this->publishedPayload(
            ContentKind::Navigation,
            'primary',
            $lockForUpdate,
        );

        return $this->links($payload['items'] ?? config('site.navigation', []));
    }

    /**
     * @return array{
     *     company: array<int, array{label: string, href: string}>,
     *     resources: array<int, array{label: string, href: string}>,
     *     emergency: array{heading: string, body: string}
     * }
     */
    public function footer(bool $lockForUpdate = false): array
    {
        $payload = $this->publishedPayload(
            ContentKind::Footer,
            'site-footer',
            $lockForUpdate,
        );

        return [
            // Copper Eagle's footer links, until the owner publishes a footer.
            'company' => $this->links($payload['company'] ?? [
                ['label' => 'About', 'href' => '/about-jordan-walker-master-plumber-utah'],
                ['label' => 'Reviews', 'href' => '/reviews-copper-eagle-plumbing'],
                ['label' => 'FAQ', 'href' => '/plumbing-faq-salt-lake-city-utah'],
                ['label' => 'Blog', 'href' => '/blog'],
                ['label' => 'Contact', 'href' => '/contact-copper-eagle-plumbing'],
                ['label' => '24/7 Emergencies', 'href' => '/24-7-emergency-plumber-salt-lake-utah'],
            ]),
            'resources' => $this->links($payload['resources'] ?? [
                ['label' => 'Privacy Policy', 'href' => '/privacy-policy'],
                // Text-message (A2P) programs need both legal pages one click away.
                ['label' => 'Terms and Conditions', 'href' => '/terms-and-conditions'],
                ['label' => 'Sitemap', 'href' => '/sitemap.xml'],
            ]),
            // The company's own emergency note, from its contact page.
            'emergency' => [
                'heading' => 'Emergency service',
                'body' => 'Our expert Emergency Maintenance team are available 24 Hours 7 days a week, 365 days a year to attend to your problems promptly!',
            ],
        ];
    }

    public function referencesPath(
        string $path,
        bool $lockForUpdate = false,
    ): bool {
        $links = [
            ...$this->navigation($lockForUpdate),
            ...$this->footer($lockForUpdate)['company'],
            ...$this->footer($lockForUpdate)['resources'],
            ...$this->publishedPageLinks($lockForUpdate),
        ];

        return collect($links)->contains(function (array $link) use ($path): bool {
            $href = $link['href'];
            $isRootRelative = str_starts_with($href, '/')
                && ! str_starts_with($href, '//');
            $isSameOriginAbsolute = $this->hasSameOrigin(
                $href,
                (string) config('app.url'),
            );

            if (! $isRootRelative && ! $isSameOriginAbsolute) {
                return false;
            }

            $hrefPath = parse_url($href, PHP_URL_PATH);

            return is_string($hrefPath)
                && rtrim($hrefPath, '/') === rtrim($path, '/');
        });
    }

    /**
     * Serialize publication-graph mutations in a deterministic row order.
     *
     * Page publication/archive and navigation/footer publication all take
     * this lock before validating public links, so neither can commit from a
     * stale view of the other.
     */
    public function lockPublicationGraph(): void
    {
        ContentEntry::query()
            ->whereIn('kind', [
                ContentKind::Page->value,
                ContentKind::Navigation->value,
                ContentKind::Footer->value,
            ])
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
    }

    /**
     * @return array<string, mixed>
     */
    private function publishedPayload(
        ContentKind $kind,
        string $slug,
        bool $lockForUpdate = false,
    ): array {
        if (! Schema::hasTable('content_entries')) {
            return [];
        }

        $query = ContentEntry::query()
            ->with('publishedRevision')
            ->where('kind', $kind->value)
            ->where('slug', $slug)
            ->whereNotNull('published_revision_id');
        $entry = $lockForUpdate
            ? $query->lockForUpdate()->first()
            : $query->first();

        return $entry?->publishedRevision?->payload ?? [];
    }

    /**
     * @return array<int, array{label: string, href: string}>
     */
    private function publishedPageLinks(bool $lockForUpdate = false): array
    {
        if (! Schema::hasTable('content_entries')) {
            return [];
        }

        $query = ContentEntry::query()
            ->with('publishedRevision')
            ->where('kind', ContentKind::Page->value)
            ->whereNotNull('published_revision_id')
            ->orderBy('id');
        $entries = $lockForUpdate
            ? $query->lockForUpdate()->get()
            : $query->get();
        $links = [];

        foreach ($entries as $entry) {
            $payload = $entry->publishedRevision?->payload;

            if (! is_array($payload)) {
                continue;
            }

            $links = [...$links, ...$this->payloadLinks($payload)];
        }

        return $links;
    }

    /**
     * @return array<int, array{label: string, href: string}>
     */
    private function links(mixed $items, int $depth = 0): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item)
                && is_string($item['label'] ?? null)
                && is_string($item['href'] ?? null))
            ->map(function (array $item) use ($depth): array {
                $link = [
                    'label' => trim($item['label']),
                    'href' => $item['href'],
                ];

                // One level only. A navigation menu that nests further is a
                // sitemap, and it cannot be operated on a touch screen.
                if ($depth === 0) {
                    $link['children'] = $this->links($item['children'] ?? [], 1);
                }

                return $link;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, href: string}>
     */
    private function payloadLinks(array $payload): array
    {
        $links = [];

        foreach ($payload as $key => $value) {
            if ($key === 'breadcrumbs' && is_array($value) && array_is_list($value)) {
                foreach (array_slice($value, 0, -1) as $crumb) {
                    if (is_array($crumb) && is_string($crumb['url'] ?? null)) {
                        $links[] = [
                            'label' => is_string($crumb['name'] ?? null)
                                ? trim($crumb['name'])
                                : $crumb['url'],
                            'href' => $crumb['url'],
                        ];
                    }
                }

                continue;
            }

            if (in_array($key, ['href', 'url'], true) && is_string($value)) {
                $links[] = [
                    'label' => is_string($payload['label'] ?? null)
                        ? trim($payload['label'])
                        : (is_string($payload['title'] ?? null)
                            ? trim($payload['title'])
                            : $value),
                    'href' => $value,
                ];
            }

            if (is_array($value)) {
                $links = [...$links, ...$this->payloadLinks($value)];
            }
        }

        return $links;
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
}
