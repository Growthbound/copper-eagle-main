@props([
    'id' => 'service-hero',
    'eyebrow' => null,
    'heading',
    'body' => null,
    'primaryAction',
    'secondaryAction' => null,
    'image' => null,
])

@php
    $data = app(\App\Content\BlockData::class);
    $primary = $data->link($primaryAction);
    $secondary = $data->link($secondaryAction);
    $validImage = $data->media($image);
@endphp

<section id="{{ $id }}" class="service-hero edge-panel dotted-panel" aria-labelledby="{{ $id }}-title">
    <x-site-header :cta="$primary" />
    <div class="service-hero-inner {{ $validImage ? '' : 'is-without-media' }}">
        <div>
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
                        <a class="button button-orange" href="{{ $primary['href'] }}">{{ $primary['label'] }} <span aria-hidden="true">→</span></a>
                    @endif
                    @if ($secondary)
                        <a href="{{ $secondary['href'] }}">{{ $secondary['label'] }}</a>
                    @endif
                    <x-call-link class="hero-call" prefix="or call" />
                </div>
            @endif
        </div>
        @if ($validImage)
            <img src="{{ $data->assetUrl($validImage['src']) }}" alt="{{ $validImage['alt'] }}" width="{{ $validImage['width'] }}" height="{{ $validImage['height'] }}" fetchpriority="high">
        @endif
    </div>
</section>
