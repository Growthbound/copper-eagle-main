@props([
    'id' => 'visit-expectations',
    'eyebrow' => null,
    'heading',
    'body' => null,
    'items' => [],
])

@php
    $data = app(\App\Content\BlockData::class);
    $validItems = collect(is_array($items) ? $items : [])
        ->filter(fn (mixed $item): bool => is_array($item)
            && is_string($item['label'] ?? null)
            && trim($item['label']) !== ''
            && is_string($item['description'] ?? null)
            && trim($item['description']) !== '')
        ->values();
@endphp

@if ($validItems->isNotEmpty())
    <section id="{{ $id }}" class="visit-expectations-section section-space" aria-labelledby="{{ $id }}-title">
        <header class="local-section-heading">
            @if (filled($eyebrow))
                <p class="eyebrow text-accent">{{ $eyebrow }}</p>
            @endif
            <h2 id="{{ $id }}-title">{{ $heading }}</h2>
            @if (filled($body))
                <p>{{ $body }}</p>
            @endif
        </header>
        <div class="visit-expectations-grid">
            @foreach ($validItems as $item)
                <article>
                    <x-marketing-icon :name="$data->icon($item['icon'] ?? null)" />
                    <h3>{{ $item['label'] }}</h3>
                    <p>{{ $item['description'] }}</p>
                </article>
            @endforeach
        </div>
    </section>
@endif
