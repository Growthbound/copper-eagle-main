@props([
    'id' => 'photo-gallery',
    'eyebrow' => null,
    'heading',
    'images' => [],
])

@php
    $data = app(\App\Content\BlockData::class);
    $validImages = collect(is_array($images) ? $images : [])
        ->map(fn (mixed $image): ?array => $data->media($image))
        ->filter()
        ->values();
@endphp

@if ($validImages->isNotEmpty())
    <section id="{{ $id }}" class="photo-gallery-section section-space" aria-labelledby="{{ $id }}-title" data-photo-gallery>
        <header class="evidence-section-heading">
            @if (filled($eyebrow))
                <p class="eyebrow text-accent">{{ $eyebrow }}</p>
            @endif
            <h2 id="{{ $id }}-title">{{ $heading }}</h2>
        </header>
        <div class="photo-gallery-grid">
            @foreach ($validImages as $image)
                @php($dialogId = $id.'-image-'.$loop->index)
                <figure>
                    <button type="button" data-gallery-open="{{ $dialogId }}" aria-label="Enlarge: {{ $image['alt'] }}">
                        <img src="{{ $data->assetUrl($image['src']) }}" alt="{{ $image['alt'] }}" width="{{ $image['width'] }}" height="{{ $image['height'] }}" loading="lazy">
                    </button>
                    @if (filled($image['caption'] ?? null))
                        <figcaption>{{ $image['caption'] }}</figcaption>
                    @endif
                </figure>
                <dialog id="{{ $dialogId }}" class="photo-gallery-dialog">
                    <button type="button" data-gallery-close aria-label="Close image">Close</button>
                    <img src="{{ $data->assetUrl($image['src']) }}" alt="{{ $image['alt'] }}" width="{{ $image['width'] }}" height="{{ $image['height'] }}" loading="lazy">
                    @if (filled($image['caption'] ?? null))
                        <p>{{ $image['caption'] }}</p>
                    @endif
                </dialog>
            @endforeach
        </div>
    </section>
@endif
