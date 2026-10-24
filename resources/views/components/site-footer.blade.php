{{--
    Site chrome, not a block. The layout renders this on every marketing page.

    Contact details come from config/site.php, which the admin overrides at
    runtime, and each one is hidden when blank rather than rendered empty.
--}}
@props(['footerLinks' => null])

@php($site = config('site'))
@php($serviceAreas = app(App\Content\AreaRepository::class)->all())
@php($footerLinks ??= app(App\Content\SiteStructureRepository::class)->footer())

<footer id="contact" class="site-footer">
    <div class="footer-brand">
        <x-brand-mark />
        <p>{{ $site['description'] }}</p>
    </div>

    <div>
        <h2>Company</h2>
        @foreach ($footerLinks['company'] as $item)
            <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
        @endforeach
    </div>

    <div>
        <h2>Resources</h2>
        @foreach ($footerLinks['resources'] as $item)
            <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
        @endforeach
    </div>

    @if (filled($site['phone']) || filled($site['email']) || filled($site['address']) || $serviceAreas->isNotEmpty() || filled($site['social_links']))
        <div class="footer-details">
            @if (filled($site['phone']) || filled($site['email']) || filled($site['address']))
                <address>
                    <h2>Contact</h2>
                    @if (filled($site['address']))
                        <span data-contact-address>{{ $site['address'] }}</span>
                    @endif
                    @if (filled($site['email']))
                        <a href="mailto:{{ $site['email'] }}">{{ $site['email'] }}</a>
                    @endif
                    <x-call-link />
                </address>
            @endif

            @if ($serviceAreas->isNotEmpty())
                <div>
                    <h2>Service areas</h2>
                    @foreach ($serviceAreas as $area)
                        <a href="{{ $area->url() }}">{{ $area->label() }}</a>
                    @endforeach
                </div>
            @endif

            @if (filled($site['social_links']))
                <div>
                    <h2>Follow</h2>
                    @foreach ($site['social_links'] as $social)
                        <a href="{{ $social['url'] }}">{{ $social['label'] }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <p class="copyright">© {{ now()->year }} {{ $site['name'] }}. All rights reserved.</p>
</footer>
