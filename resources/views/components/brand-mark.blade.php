@php
    $siteName = trim((string) config('site.name', config('app.name')));
    // Shared with the business entity and the Open Graph card, so the header
    // mark and the logo Google reads can never be two different images.
    $logo = App\Support\SiteLogo::resolve();
@endphp

<a href="{{ route('home') }}" class="brand-mark" aria-label="{{ $siteName }} home">
    @if ($logo)
        <img
            src="{{ $logo['url'] }}"
            alt="{{ $siteName }}"
            width="{{ $logo['width'] }}"
            height="{{ $logo['height'] }}"
        >
    @else
        <span>{{ $siteName }}</span>
    @endif
</a>
