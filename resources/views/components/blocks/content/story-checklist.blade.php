@props([
    'id' => 'story-checklist',
    'eyebrow' => null,
    'heading',
    'body' => null,
    'items' => [],
    'image' => null,
    'inset' => null,
])

@php
    $data = app(\App\Content\BlockData::class);
@endphp

@php
    $hasImage = is_array($image)
        && filled($image['src'] ?? null)
        && filled($image['alt'] ?? null)
        && isset($image['width'], $image['height'])
        && (int) $image['width'] > 0
        && (int) $image['height'] > 0;

    $hasInset = $hasImage
        && is_array($inset)
        && filled($inset['src'] ?? null)
        && filled($inset['alt'] ?? null)
        && isset($inset['width'], $inset['height'])
        && (int) $inset['width'] > 0
        && (int) $inset['height'] > 0;

    $displayItems = is_array($items) ? $items : [];
@endphp

<section
    id="{{ $id }}"
    @class([
        'story-checklist-panel',
        'section-space',
        'is-without-media' => ! $hasImage,
    ])
    data-content-variant="story-checklist"
    aria-labelledby="{{ $id }}-title"
>
    @if ($hasImage)
        <div class="story-checklist-media">
            <img
                class="story-checklist-main"
                src="{{ $data->assetUrl($image['src']) }}"
                alt="{{ $image['alt'] }}"
                width="{{ (int) $image['width'] }}"
                height="{{ (int) $image['height'] }}"
                loading="lazy"
            >

            @if ($hasInset)
                <img
                    class="story-checklist-inset"
                    src="{{ $data->assetUrl($inset['src']) }}"
                    alt="{{ $inset['alt'] }}"
                    width="{{ (int) $inset['width'] }}"
                    height="{{ (int) $inset['height'] }}"
                    loading="lazy"
                >
            @endif
        </div>
    @endif

    <div class="story-checklist-copy">
        @if (filled($eyebrow))
            <p class="eyebrow text-accent">{{ $eyebrow }}</p>
        @endif

        <h2 id="{{ $id }}-title">{{ $heading }}</h2>

        @if (filled($body))
            <p>{{ $body }}</p>
        @endif

        @if ($displayItems !== [])
            <ul class="story-checklist-list">
                @foreach ($displayItems as $item)
                    <li>
                        <x-check-icon class="story-checklist-icon" />
                        <span>{{ $item }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
