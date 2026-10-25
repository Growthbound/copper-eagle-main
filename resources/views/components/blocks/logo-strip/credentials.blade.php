@props([
    'id' => 'credentials',
    'eyebrow' => null,
    'heading',
    'items' => [],
])

@php
    $data = app(\App\Content\BlockData::class);
    $validItems = collect(is_array($items) ? $items : [])
        ->map(function (mixed $item) use ($data): ?array {
            if (! is_array($item)
                || ! is_string($item['name'] ?? null)
                || trim($item['name']) === ''
                || $data->source($item) === null
                || ($image = $data->media($item['image'] ?? null)) === null) {
                return null;
            }
            return [...$item, 'image' => $image];
        })
        ->filter()
        ->values();
@endphp

@if ($validItems->isNotEmpty())
    <section id="{{ $id }}" class="credentials-section section-space" aria-labelledby="{{ $id }}-title">
        <header class="evidence-section-heading">
            @if (filled($eyebrow))
                <p class="eyebrow text-accent">{{ $eyebrow }}</p>
            @endif
            <h2 id="{{ $id }}-title">{{ $heading }}</h2>
        </header>
        <ul class="credentials-grid">
            @foreach ($validItems as $item)
                <li>
                    <img src="{{ $data->assetUrl($item['image']['src']) }}" alt="{{ $item['image']['alt'] }}" width="{{ $item['image']['width'] }}" height="{{ $item['image']['height'] }}" loading="lazy">
                    <strong>{{ $item['name'] }}</strong>
                    <a href="{{ $item['source_url'] }}" rel="noopener noreferrer">{{ $item['source_label'] }}</a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
