@props([
    'id' => 'proof-stats',
    'eyebrow' => null,
    'heading',
    'items' => [],
])

@php
    $data = app(\App\Content\BlockData::class);
    $validItems = collect(is_array($items) ? $items : [])
        ->filter(fn (mixed $item): bool => is_array($item)
            && is_string($item['value'] ?? null)
            && trim($item['value']) !== ''
            && is_string($item['label'] ?? null)
            && trim($item['label']) !== ''
            && $data->source($item) !== null)
        ->values();
@endphp

@if ($validItems->isNotEmpty())
    <section id="{{ $id }}" class="proof-stats-section section-space" aria-labelledby="{{ $id }}-title">
        <header class="evidence-section-heading">
            @if (filled($eyebrow))
                <p class="eyebrow text-accent">{{ $eyebrow }}</p>
            @endif
            <h2 id="{{ $id }}-title">{{ $heading }}</h2>
        </header>
        <dl class="proof-stats-grid">
            @foreach ($validItems as $item)
                <div>
                    <dd>{{ $item['value'] }}</dd>
                    <dt>{{ $item['label'] }}</dt>
                    @if (filled($item['context'] ?? null))
                        <p>{{ $item['context'] }}</p>
                    @endif
                    <a href="{{ $item['source_url'] }}" rel="noopener noreferrer">{{ $item['source_label'] }}</a>
                </div>
            @endforeach
        </dl>
    </section>
@endif
