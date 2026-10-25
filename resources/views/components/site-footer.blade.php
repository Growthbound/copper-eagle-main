{{--
    Site chrome, not a block. The layout renders this on every marketing page.

    Deep blue, with the stacked logo in a centered notch (see "Site chrome" in
    docs/brand/design-system.md). Services and areas come from their records;
    contact details from site settings, each hidden when blank.
--}}
@props(['footerLinks' => null])

@php
    $site = config('site');
    $services = app(App\Content\ServiceRepository::class)->all();
    $serviceAreas = app(App\Content\AreaRepository::class)->all();
    $footerLinks ??= app(App\Content\SiteStructureRepository::class)->footer();
    $stackedLogo = App\Support\SiteLogo::stacked();
@endphp

<footer id="contact" class="site-footer edge-panel">
    <div class="site-footer-inner">
        <nav class="site-footer-column" aria-label="Services">
            <h2>Services</h2>
            @forelse ($services as $service)
                <a href="{{ $service->url() }}">{{ $service->name() }}</a>
            @empty
                <a href="{{ route('services.index') }}">All services</a>
            @endforelse
        </nav>

        <div class="site-footer-center">
            <a href="{{ route('home') }}" class="site-footer-logo" aria-label="{{ $site['name'] }} home">
                @if ($stackedLogo)
                    <img src="{{ $stackedLogo['url'] }}" alt="{{ $site['name'] }}" width="{{ $stackedLogo['width'] }}" height="{{ $stackedLogo['height'] }}" loading="lazy">
                @else
                    <span>{{ $site['name'] }}</span>
                @endif
            </a>

            <x-call-link class="button button-orange site-footer-call" prefix="Call us" />

            @if (filled($site['description']))
                <p class="site-footer-description">{{ $site['description'] }}</p>
            @endif

            @if (filled($site['address']) || filled($site['email']))
                <address>
                    @if (filled($site['address']))
                        <span data-contact-address>{{ $site['address'] }}</span>
                    @endif
                    @if (filled($site['email']))
                        <a href="mailto:{{ $site['email'] }}">{{ $site['email'] }}</a>
                    @endif
                </address>
            @endif
        </div>

        <div class="site-footer-column">
            <nav aria-label="Company">
                <h2>Company</h2>
                @foreach ($footerLinks['company'] as $item)
                    <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
                @endforeach
            </nav>

            @if (filled($site['social_links']))
                <nav class="site-footer-social" aria-label="Follow {{ $site['name'] }}">
                    <h2>Follow us</h2>
                    @foreach ($site['social_links'] as $social)
                        <a href="{{ $social['url'] }}">{{ $social['label'] }}</a>
                    @endforeach
                </nav>
            @endif
        </div>
    </div>

    @if ($serviceAreas->isNotEmpty())
        <nav class="site-footer-areas" aria-label="Service areas">
            <h2>Serving</h2>
            @foreach ($serviceAreas as $area)
                <a href="{{ $area->url() }}">{{ $area->name() }}</a>
            @endforeach
        </nav>
    @endif

    <div class="site-footer-bottom">
        <p class="copyright">© {{ now()->year }} {{ $site['name'] }}. All rights reserved.</p>
        <nav aria-label="Site resources">
            @foreach ($footerLinks['resources'] as $item)
                <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
            @endforeach
        </nav>
    </div>
</footer>
