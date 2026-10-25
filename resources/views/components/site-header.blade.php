{{--
    Site chrome, not a block. Every hero renders this first, so each page opens
    with the same white bar: navigation left, logo centered, call button right
    (see "Site chrome" in docs/brand/design-system.md).

    Navigation comes from the published site structure (or config/site.php),
    and the call button from site settings, so no page repeats either.
--}}
@props([
    'cta' => null,
    'navigation' => null,
])

@php
    $navigation ??= app(App\Content\SiteStructureRepository::class)->navigation();
    $currentPath = '/'.trim(request()->path(), '/');
    $isCurrent = fn (string $href): bool => '/'.trim((string) parse_url($href, PHP_URL_PATH), '/') === $currentPath;
@endphp

<header class="site-header edge-panel">
    <div class="site-header-inner">
        <nav class="primary-nav" aria-label="Primary navigation">
            @foreach ($navigation as $item)
                @if (filled($item['children'] ?? []))
                    {{--
                        The parent stays a real link to the hub, so the menu is a
                        shortcut rather than a replacement. Opening on hover and on
                        focus-within keeps it usable from the keyboard with no
                        JavaScript involved.
                    --}}
                    <div class="nav-has-menu">
                        <a href="{{ $item['href'] }}" @if ($isCurrent($item['href'])) aria-current="page" @endif>{{ $item['label'] }}</a>
                        <ul class="nav-menu">
                            @foreach ($item['children'] as $child)
                                <li><a href="{{ $child['href'] }}">{{ $child['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <a href="{{ $item['href'] }}" @if ($isCurrent($item['href'])) aria-current="page" @endif>{{ $item['label'] }}</a>
                @endif
            @endforeach
        </nav>

        <x-brand-mark />

        <div class="header-actions">
            @if (filled(config('site.phone')))
                <x-call-link class="button button-orange header-call" prefix="Call or text" />
            @elseif (filled($cta))
                <a class="button button-orange header-cta" href="{{ $cta['href'] }}">
                    {{ $cta['label'] }} <span aria-hidden="true">→</span>
                </a>
            @endif
        </div>

        <details class="mobile-nav">
            <summary aria-label="Toggle navigation"><span></span><span></span><span></span></summary>
            <nav aria-label="Mobile navigation">
                @foreach ($navigation as $item)
                    <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
                    @foreach ($item['children'] ?? [] as $child)
                        <a class="mobile-nav-child" href="{{ $child['href'] }}">{{ $child['label'] }}</a>
                    @endforeach
                @endforeach
                <x-call-link class="mobile-nav-call" prefix="Call or text" />
            </nav>
        </details>
    </div>
</header>
