{{--
    Interior page hero: deep blue band (optionally over a darkened photo),
    centered uppercase eyebrow, centered white H1, and a white chevron rising
    into the next section. The photo is a decorative backdrop, so it renders
    with empty alt text; the H1 carries the meaning.

    An optional `video` plays muted and looping behind the same overlay, over
    the photo when there is one (see hero/partials/background-video). Without
    a photo the poster still is the fallback.
--}}
@props([
    'id' => 'interior-hero',
    'eyebrow' => null,
    'heading',
    'body' => null,
    'primaryAction' => null,
    'secondaryAction' => null,
    'image' => null,
    'video' => null,
])

@php
    $data = app(\App\Content\BlockData::class);
    $primary = $data->link($primaryAction);
    $secondary = $data->link($secondaryAction);
    $backdrop = $data->media($image);
    $backgroundVideo = $data->backgroundVideo($video);
@endphp

<x-site-header :cta="$primary" />

<section
    id="{{ $id }}"
    @class(['interior-hero', 'edge-panel', 'has-backdrop' => $backdrop !== null || $backgroundVideo !== null])
    aria-labelledby="{{ $id }}-title"
>
    @if ($backdrop)
        <img
            class="interior-hero-backdrop"
            src="{{ $data->assetUrl($backdrop['src']) }}"
            alt=""
            width="{{ $backdrop['width'] }}"
            height="{{ $backdrop['height'] }}"
            fetchpriority="high"
        >
    @endif

    @if ($backgroundVideo)
        @include('components.blocks.hero.partials.background-video', ['video' => $backgroundVideo, 'class' => 'interior-hero-video'])
    @endif

    <div class="interior-hero-inner">
        @if (filled($eyebrow))
            <p class="eyebrow">{{ $eyebrow }}</p>
        @endif
        <h1 id="{{ $id }}-title">{{ $heading }}</h1>
        @if (filled($body))
            <p>{{ $body }}</p>
        @endif
        @if ($primary || $secondary)
            <div class="hero-variant-actions">
                @if ($primary)
                    <a class="button button-orange" href="{{ $primary['href'] }}">{{ $primary['label'] }}</a>
                @endif
                @if ($secondary)
                    <a href="{{ $secondary['href'] }}">{{ $secondary['label'] }}</a>
                @endif
                <x-call-link class="hero-call" prefix="or call" />
            </div>
        @endif
    </div>
</section>
