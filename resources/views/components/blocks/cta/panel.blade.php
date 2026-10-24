@props([
    'id' => 'cta',
    'eyebrow' => null,
    'heading',
    'cta' => null,
    'image' => null,
])

@php
    $data = app(\App\Content\BlockData::class);
@endphp

<section id="{{ $id }}" class="cta-panel edge-panel" aria-labelledby="{{ $id }}-title">
    <div>
        @if (filled($eyebrow))
            <p class="eyebrow">{{ $eyebrow }}</p>
        @endif

        <h2 id="{{ $id }}-title">{{ $heading }}</h2>

        @if (filled($cta))
            <a class="button button-teal" href="{{ $cta['href'] }}">
                {{ $cta['label'] }} <span aria-hidden="true">→</span>
            </a>
        @endif

        <x-call-link class="cta-call" prefix="or call" />
    </div>

    @if (filled($image))
        <img
            src="{{ $data->assetUrl($image['src']) }}"
            alt="{{ $image['alt'] }}"
            width="{{ $image['width'] }}"
            height="{{ $image['height'] }}"
            loading="lazy"
        >
    @endif
</section>
