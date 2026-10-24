{{--
    Site chrome, not a block. Hero variants include this so the navigation sits
    on the hero's background rather than on its own strip.

    Navigation and the call-to-action come from config/site.php, so every page
    that uses a hero gets the same header without repeating it in page files.
--}}
@props([
    'cta' => null,
    'navigation' => null,
])

@php($navigation ??= app(App\Content\SiteStructureRepository::class)->navigation())

<header class="site-header">
    <x-brand-mark />

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
                    <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
                    <ul class="nav-menu">
                        @foreach ($item['children'] as $child)
                            <li><a href="{{ $child['href'] }}">{{ $child['label'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @else
                <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
            @endif
        @endforeach
    </nav>

    <details class="mobile-nav">
        <summary aria-label="Toggle navigation"><span></span><span></span><span></span></summary>
        <nav aria-label="Mobile navigation">
            @foreach ($navigation as $item)
                <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
                @foreach ($item['children'] ?? [] as $child)
                    <a class="mobile-nav-child" href="{{ $child['href'] }}">{{ $child['label'] }}</a>
                @endforeach
            @endforeach
        </nav>
    </details>

    <div class="header-actions">
        <x-call-link class="header-call" />

        @if (filled($cta))
            <a class="button button-orange header-cta" href="{{ $cta['href'] }}">
                {{ $cta['label'] }} <span aria-hidden="true">→</span>
            </a>
        @endif
    </div>
</header>
