@props([
    'id' => 'image-split-cta',
    'eyebrow' => null,
    'heading',
    'body' => null,
    'cta' => null,
    'image' => null,
])

@php
    $data = app(\App\Content\BlockData::class);
@endphp

@php
    $hasCta = is_array($cta)
        && filled($cta['label'] ?? null)
        && filled($cta['href'] ?? null);

    $hasImage = is_array($image)
        && filled($image['src'] ?? null)
        && filled($image['alt'] ?? null)
        && is_numeric($image['width'] ?? null)
        && is_numeric($image['height'] ?? null)
        && (int) $image['width'] > 0
        && (int) $image['height'] > 0;

    $fit = $hasImage && ($image['fit'] ?? null) === 'contain' ? 'contain' : 'cover';
@endphp

<section
    id="{{ $id }}"
    @class([
        'cta-image-split',
        'is-without-media' => ! $hasImage,
    ])
    data-cta-variant="image-split"
    aria-labelledby="{{ $id }}-title"
>
    <div class="cta-image-split-inner">
        <div class="cta-image-split-copy">
            @if (filled($eyebrow))
                <p class="eyebrow">{{ $eyebrow }}</p>
            @endif

            <h2 id="{{ $id }}-title">{{ $heading }}</h2>

            @if (filled($body))
                <p>{{ $body }}</p>
            @endif

            @if ($hasCta)
                <a class="button button-orange" href="{{ $cta['href'] }}">
                    {{ $cta['label'] }}
                    <span aria-hidden="true">→</span>
                </a>
            @endif
        </div>

        @if ($hasImage)
            <div class="cta-image-split-media is-{{ $fit }}">
                <img
                    src="{{ $data->assetUrl($image['src']) }}"
                    alt="{{ $image['alt'] }}"
                    width="{{ (int) $image['width'] }}"
                    height="{{ (int) $image['height'] }}"
                    loading="lazy"
                >
                <span class="cta-image-split-accent" aria-hidden="true"></span>
                <span class="cta-image-split-spark" aria-hidden="true"></span>
            </div>
        @endif
    </div>
</section>
