@props([
    'title' => null,
    'description' => null,
    'noindex' => false,
    'canonical' => true,
    'openGraph' => [],
    'schema' => [],
    'showFooter' => true,
    'footerLinks' => null,
    'tracking' => true,
])

@php
    $canonicalUrl = is_string($canonical) ? $canonical : url()->current();

    if ($canonical !== false && ! $noindex) {
        $schema = App\Support\StructuredData::withWebPage(
            $schema,
            $canonicalUrl,
            $title ?: config('site.name'),
            $description ?: config('site.description'),
        );
    }
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $description ?: config('site.description') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png" sizes="512x512">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}" sizes="180x180">
    @if ($canonical !== false)
        <link rel="canonical" href="{{ $canonicalUrl }}">
    @endif
    {{-- A page may hold itself back; a non-canonical host holds back everything on it. --}}
    @if ($noindex || ! App\Support\SearchVisibility::indexable())
        <meta name="robots" content="noindex, nofollow">
    @endif
    <title>{{ $title ? $title.' | '.config('site.name') : config('site.name') }}</title>
    @php
        /*
         * Open Graph used to render only for pages that opted in, which meant
         * almost none of them did. A page that supplies its own card still wins
         * every field; the rest now fall back to what the page already knows
         * about itself, so every shared link carries a title, a summary and an
         * image instead of whatever the platform decides to scrape.
         */
        $ogDescription = $openGraph['description'] ?? $description ?: config('site.description');
        $ogImage = $openGraph['image'] ?? null;

        if (! is_array($ogImage) && ($siteLogo = App\Support\SiteLogo::resolve())) {
            $ogImage = [
                'src' => $siteLogo['url'],
                'alt' => config('site.name'),
                'width' => $siteLogo['width'],
                'height' => $siteLogo['height'],
            ];
        }

        /*
         * The large card has a floor of 300x157 and expects something near 2:1.
         * Size alone is not enough to judge that: a square logo clears both
         * thresholds and still gets cropped or dropped, and a wordmark clears
         * neither. Both cases want the summary card, where a small image sits
         * beside the text — which is what a logo is actually for.
         */
        $ogRatio = is_array($ogImage) && ($ogImage['height'] ?? 0) > 0
            ? ($ogImage['width'] ?? 0) / $ogImage['height']
            : 0;

        $ogLargeCard = is_array($ogImage)
            && ($ogImage['width'] ?? 0) >= 300
            && ($ogImage['height'] ?? 0) >= 157
            && $ogRatio >= 1.5
            && $ogRatio <= 3.0;
    @endphp
    <meta property="og:type" content="{{ $openGraph['type'] ?? 'website' }}">
    <meta property="og:site_name" content="{{ config('site.name') }}">
    <meta property="og:title" content="{{ $openGraph['title'] ?? ($title ?: config('site.name')) }}">
    @if (filled($ogDescription))
        <meta property="og:description" content="{{ $ogDescription }}">
    @endif
    <meta property="og:url" content="{{ $openGraph['url'] ?? url()->current() }}">
    @if (is_array($ogImage))
        <meta property="og:image" content="{{ str_starts_with($ogImage['src'], '/') ? url($ogImage['src']) : $ogImage['src'] }}">
        <meta property="og:image:alt" content="{{ $ogImage['alt'] }}">
        <meta property="og:image:width" content="{{ $ogImage['width'] }}">
        <meta property="og:image:height" content="{{ $ogImage['height'] }}">
    @endif
    <meta name="twitter:card" content="{{ $ogLargeCard ? 'summary_large_image' : 'summary' }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($themeOverrides = App\Support\Theme::cssOverrides())
        <style>{{ $themeOverrides }}</style>
    @endif
    @livewireStyles
    <x-structured-data :nodes="$schema" />
    @if ($tracking)
        <x-tracking.head />
    @endif
</head>
<body class="bg-page text-text antialiased">
    <a class="skip-link" href="#main-content">Skip to content</a>
    <x-announcement-bar />

    {{--
        Calling is the conversion path for this trade, so the number stays
        within thumb reach on a phone rather than living only in the footer.
        Hidden on larger screens, where the header call is already visible.
    --}}
    @if (filled(config('site.phone')))
        <aside class="sticky-mobile-call" aria-label="Call {{ config('site.name') }}">
            <x-call-link prefix="Call" />
        </aside>
    @endif

    <main id="main-content" class="page-shell">
        {{ $slot }}

        @if ($showFooter)
            <x-site-footer :footer-links="$footerLinks" />
        @endif
    </main>

    @livewireScripts
    @if ($tracking)
        <x-tracking.body />
    @endif
</body>
</html>
