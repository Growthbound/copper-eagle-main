{{--
    The home page hero ("Page templates" in docs/brand/design-system.md): the
    site header, the service icon strip, then a full-bleed photo under a
    deep-blue overlay with the page's only H1, left-aligned in the page width,
    and one copper action. A white chevron with a copper line rises into the
    next section.

    The strip reads the service records (see service-cards/icon-strip); set
    `serviceStrip` to false to leave it out.

    An optional `video` plays muted and looping over the photo, under the same
    overlay. It is decorative (hidden from assistive technology), its source is
    attached by resources/js/app.js only when the visitor allows motion, and
    otherwise its poster still shows. The photo stays the priority image.
--}}
@props([
    'id' => 'hero',
    'eyebrow' => null,
    'heading',
    'emphasis' => null,
    'body' => null,
    'primaryAction',
    'image',
    'video' => null,
    'serviceStrip' => true,
])

@php
    $data = app(\App\Content\BlockData::class);
    $primary = $data->link($primaryAction);
    $photo = $data->media($image);
    $backgroundVideo = $data->backgroundVideo($video);
@endphp

<x-site-header :cta="$primary" />

@if ($serviceStrip !== false)
    <x-blocks.service-cards.icon-strip />
@endif

<section id="{{ $id }}" @class(['photo-hero', 'edge-panel', 'is-without-media' => $photo === null]) aria-labelledby="{{ $id }}-title">
    @if ($photo)
        <img
            class="photo-hero-image"
            src="{{ $data->assetUrl($photo['src']) }}"
            alt="{{ $photo['alt'] }}"
            width="{{ $photo['width'] }}"
            height="{{ $photo['height'] }}"
            fetchpriority="high"
        >
    @endif

    @if ($backgroundVideo)
        @include('components.blocks.hero.partials.background-video', ['video' => $backgroundVideo, 'class' => 'photo-hero-video'])
    @endif

    <div class="photo-hero-inner">
        <div class="photo-hero-copy">
            @if (filled($eyebrow))
                <p class="eyebrow">{{ $eyebrow }}</p>
            @endif
            <h1 id="{{ $id }}-title"><x-content.emphasized :text="$heading" :emphasis="$emphasis" /></h1>
            @if (filled($body))
                <p class="photo-hero-body">{{ $body }}</p>
            @endif
            @if ($primary)
                <a class="button button-orange" href="{{ $primary['href'] }}">{{ $primary['label'] }}</a>
            @endif
        </div>
    </div>

    <span class="photo-hero-edge" aria-hidden="true"></span>
</section>
