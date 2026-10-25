{{--
    The "What to expect" band: deep blue (optionally over a darkened photo),
    copper heading, white intro, and one column per factor under a small
    copper diamond mark. Price-free by design: it explains what shapes a
    quote, never what a quote is. The photo is a decorative backdrop, so it
    renders with empty alt text.
--}}
@props([
    'id' => 'pricing-factors',
    'eyebrow' => null,
    'heading',
    'body' => null,
    'factors' => [],
    'image' => null,
])

@php
    $validFactors = collect(is_array($factors) ? $factors : [])
        ->filter(fn (mixed $factor): bool => is_array($factor)
            && is_string($factor['label'] ?? null)
            && trim($factor['label']) !== ''
            && is_string($factor['description'] ?? null)
            && trim($factor['description']) !== '')
        ->values();
    $data = app(\App\Content\BlockData::class);
    $backdrop = $data->media($image);
@endphp

@if ($validFactors->isNotEmpty())
    <section
        id="{{ $id }}"
        @class([
            'pricing-factors-section',
            'edge-panel',
            'has-backdrop' => $backdrop !== null,
            'is-count-'.$validFactors->count(),
        ])
        aria-labelledby="{{ $id }}-title"
    >
        @if ($backdrop)
            <img
                class="pricing-factors-backdrop"
                src="{{ $data->assetUrl($backdrop['src']) }}"
                alt=""
                width="{{ $backdrop['width'] }}"
                height="{{ $backdrop['height'] }}"
                loading="lazy"
            >
        @endif

        <div class="pricing-factors-inner">
            <header class="evidence-section-heading">
                @if (filled($eyebrow))
                    <p class="eyebrow">{{ $eyebrow }}</p>
                @endif
                <h2 id="{{ $id }}-title">{{ $heading }}</h2>
                @if (filled($body))
                    <p>{{ $body }}</p>
                @endif
            </header>
            <ol class="pricing-factors-grid">
                @foreach ($validFactors as $factor)
                    <li>
                        <span class="pricing-factor-mark" aria-hidden="true"></span>
                        <h3>{{ $factor['label'] }}</h3>
                        <p>{{ $factor['description'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>
@endif
