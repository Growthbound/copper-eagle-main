@props([
    'id' => 'commitment',
    'eyebrow' => null,
    'heading',
    'body' => null,
    'points' => [],
    'cta' => null,
    'images' => [],
    'collageLabel' => 'Supporting imagery',
])

@php
    $data = app(\App\Content\BlockData::class);
@endphp

<section id="{{ $id }}" class="commitment-panel edge-panel dotted-panel" aria-labelledby="{{ $id }}-title">
    <div class="section-copy text-on-primary">
        @if (filled($eyebrow))
            <p class="eyebrow text-accent">{{ $eyebrow }}</p>
        @endif

        <h2 id="{{ $id }}-title">{{ $heading }}</h2>

        @if (filled($body))
            <p>{{ $body }}</p>
        @endif

        @if (filled($points))
            <div class="commitment-points">
                @foreach ($points as $point)
                    <span>{{ $point }}</span>
                @endforeach
            </div>
        @endif

        @if (filled($cta))
            <a class="button button-orange" href="{{ $cta['href'] }}">
                {{ $cta['label'] }} <span aria-hidden="true">→</span>
            </a>
        @endif
    </div>

    @if (filled($images))
        <div class="commitment-collage" aria-label="{{ $collageLabel }}">
            @foreach ($images as $image)
                <img
                    src="{{ $data->assetUrl($image['src']) }}"
                    alt="{{ $image['alt'] }}"
                    width="{{ $image['width'] }}"
                    height="{{ $image['height'] }}"
                    loading="lazy"
                >
            @endforeach
        </div>
    @endif
</section>
