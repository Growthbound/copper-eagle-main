{{--
    A dark full-width response band: an optional small logo, centered eyebrow,
    heading, optional body (a blank line starts a new paragraph), and an
    optional button with an "or call" line. Without a button the band is a
    statement (the live guarantee band) and shows no call line either. `tone`
    is the deep-blue band (default, optionally over a darkened photo) or the
    solid copper guarantee band with the lattice. The photo is a decorative
    backdrop, so it renders with empty alt text; the logo's alt names it.
--}}
@props([
    'id' => 'cta',
    'eyebrow' => null,
    'heading',
    'body' => null,
    'cta' => null,
    'image' => null,
    'logo' => null,
    'tone' => 'primary',
])

@php
    $data = app(\App\Content\BlockData::class);
    $action = $data->link($cta);
    $isCopper = $tone === 'copper';
    $backdrop = $isCopper ? null : $data->media($image);
    $mark = $data->media($logo);
@endphp

<section
    id="{{ $id }}"
    @class([
        'cta-panel',
        'edge-panel',
        'is-copper' => $isCopper,
        'has-backdrop' => $backdrop !== null,
    ])
    aria-labelledby="{{ $id }}-title"
>
    @if ($backdrop)
        <img
            class="cta-panel-backdrop"
            src="{{ $data->assetUrl($backdrop['src']) }}"
            alt=""
            width="{{ $backdrop['width'] }}"
            height="{{ $backdrop['height'] }}"
            loading="lazy"
        >
    @endif

    <div class="cta-panel-copy">
        @if ($mark)
            <img
                class="cta-panel-logo"
                src="{{ $data->assetUrl($mark['src']) }}"
                alt="{{ $mark['alt'] }}"
                width="{{ $mark['width'] }}"
                height="{{ $mark['height'] }}"
                loading="lazy"
            >
        @endif

        @if (filled($eyebrow))
            <p class="eyebrow">{{ $eyebrow }}</p>
        @endif

        <h2 id="{{ $id }}-title">{{ $heading }}</h2>

        @if (filled($body))
            <x-content.paragraphs :text="$body" class="cta-panel-body" />
        @endif

        @if ($action)
            <a @class(['button', 'button-teal' => $isCopper, 'button-orange' => ! $isCopper]) href="{{ $action['href'] }}">{{ $action['label'] }}</a>
        @endif

        {{-- "Or call" is an alternative to the button: none without one, and
             none when the button already places the call. --}}
        @if ($action && ! str_starts_with((string) $action['href'], 'tel:'))
            <x-call-link class="cta-call" prefix="or call" />
        @endif
    </div>
</section>
