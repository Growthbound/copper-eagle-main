@props([
    'id' => null,
    'eyebrow' => null,
    'heading',
    'body' => null,
    'checklist' => [],
    'image' => null,
    'inset' => null,
    'stamp' => null,
])

@php
    $data = app(\App\Content\BlockData::class);
@endphp

<section @if ($id)id="{{ $id }}" @endif class="split-section section-space" aria-labelledby="{{ $id ?? 'split' }}-title">
    @if (filled($image))
        <div class="image-stack">
            <img
                class="image-stack-main"
                src="{{ $data->assetUrl($image['src']) }}"
                alt="{{ $image['alt'] }}"
                width="{{ $image['width'] }}"
                height="{{ $image['height'] }}"
                loading="lazy"
            >

            @if (filled($inset))
                <img
                    class="image-stack-small"
                    src="{{ $data->assetUrl($inset['src']) }}"
                    alt="{{ $inset['alt'] }}"
                    width="{{ $inset['width'] }}"
                    height="{{ $inset['height'] }}"
                    loading="lazy"
                >
            @endif

            @if (filled($stamp))
                <span class="image-stamp" aria-hidden="true">{{ $stamp }}</span>
            @endif
        </div>
    @endif

    <div class="section-copy">
        @if (filled($eyebrow))
            <p class="eyebrow text-accent">{{ $eyebrow }}</p>
        @endif

        <h2 id="{{ $id ?? 'split' }}-title">{{ $heading }}</h2>

        @if (filled($body))
            <p>{{ $body }}</p>
        @endif

        @if (filled($checklist))
            <ul class="check-list">
                @foreach ($checklist as $point)
                    <li><x-check-icon class="check-icon-small" />{{ $point }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
