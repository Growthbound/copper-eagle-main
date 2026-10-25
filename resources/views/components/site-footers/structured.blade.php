@props([
    'id' => 'structured-footer',
    'companyLinks' => null,
    'resourceLinks' => null,
    'companyHeading' => 'Company',
    'resourceHeading' => 'Resources',
    'contactHeading' => 'Contact',
    'socialHeading' => null,
])

@php
    $site = config('site');
    $socialHeading ??= 'Follow '.($site['name'] ?? config('app.name'));

    $isSafeHref = static function (string $href): bool {
        if (Illuminate\Support\Str::startsWith($href, ['/', '#'])) {
            return true;
        }

        return filter_var($href, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($href, PHP_URL_SCHEME), ['http', 'https'], true);
    };

    $normalizeLinks = static fn (mixed $links): Illuminate\Support\Collection => collect($links)
        ->filter(fn (mixed $link): bool => is_array($link))
        ->map(fn (array $link): array => [
            'label' => trim((string) ($link['label'] ?? '')),
            'href' => trim((string) ($link['href'] ?? $link['url'] ?? '')),
        ])
        ->filter(fn (array $link): bool => $link['label'] !== ''
            && $link['href'] !== ''
            && $isSafeHref($link['href']))
        ->values();

    $company = $normalizeLinks($companyLinks ?? ($site['navigation'] ?? []));
    $resources = $normalizeLinks($resourceLinks ?? [
        ['label' => 'Services', 'href' => route('services.index')],
        ['label' => 'Service areas', 'href' => route('areas.index')],
        ['label' => 'Privacy policy', 'href' => '/privacy-policy/'],
        ['label' => 'Terms and conditions', 'href' => '/terms-and-conditions/'],
        ['label' => 'Site summary', 'href' => url('/llms.txt')],
        ['label' => 'Sitemap', 'href' => route('sitemap')],
    ]);
    $social = $normalizeLinks($site['social_links'] ?? []);

    $address = trim((string) ($site['address'] ?? ''));
    $email = trim((string) ($site['email'] ?? ''));
    $phone = trim((string) ($site['phone'] ?? ''));
    $hasContact = $address !== '' || $email !== '' || $phone !== '';
@endphp

<footer
    id="{{ $id }}"
    class="structured-footer edge-panel"
    data-footer-variant="structured"
>
    <div class="structured-footer-inner">
        <div class="structured-footer-grid">
            <div class="structured-footer-brand">
                <x-brand-mark />

                @if (filled($site['description'] ?? null))
                    <p>{{ $site['description'] }}</p>
                @endif
            </div>

            @if ($company->isNotEmpty())
                <nav class="structured-footer-group" aria-label="{{ $companyHeading }}">
                    <h2>{{ $companyHeading }}</h2>
                    @foreach ($company as $link)
                        <a href="{{ $link['href'] }}">{{ $link['label'] }}</a>
                    @endforeach
                </nav>
            @endif

            @if ($resources->isNotEmpty())
                <nav class="structured-footer-group" aria-label="{{ $resourceHeading }}">
                    <h2>{{ $resourceHeading }}</h2>
                    @foreach ($resources as $link)
                        <a href="{{ $link['href'] }}">{{ $link['label'] }}</a>
                    @endforeach
                </nav>
            @endif

            @if ($hasContact)
                <section
                    id="{{ $id }}-contact"
                    class="structured-footer-contact"
                    aria-labelledby="{{ $id }}-contact-title"
                >
                    <h2 id="{{ $id }}-contact-title">{{ $contactHeading }}</h2>

                    <address>
                        @if ($address !== '')
                            <span class="structured-footer-contact-row" data-contact-address>
                                <x-marketing-icon name="map-pin" />
                                <span>{{ $address }}</span>
                            </span>
                        @endif

                        @if ($email !== '')
                            <a class="structured-footer-contact-row" href="mailto:{{ $email }}">
                                <x-marketing-icon name="envelope" />
                                <span>{{ $email }}</span>
                            </a>
                        @endif

                        @if ($phone !== '')
                            <a class="structured-footer-contact-row" href="tel:{{ $phone }}">
                                <x-marketing-icon name="phone" />
                                <span>{{ $phone }}</span>
                            </a>
                        @endif
                    </address>
                </section>
            @endif
        </div>

        <div class="structured-footer-bottom{{ $social->isEmpty() ? ' is-social-empty' : '' }}">
            @if ($social->isNotEmpty())
                <nav
                    id="{{ $id }}-social"
                    class="structured-footer-social"
                    aria-label="{{ $socialHeading }}"
                >
                    @foreach ($social as $link)
                        <a href="{{ $link['href'] }}">{{ $link['label'] }}</a>
                    @endforeach
                </nav>
            @endif

            <p>© {{ now()->year }} {{ $site['name'] }}. All rights reserved.</p>
        </div>
    </div>
</footer>
