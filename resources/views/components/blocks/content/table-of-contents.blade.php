@props([
    'id' => 'table-of-contents',
    'eyebrow' => null,
    'heading',
    'items' => [],
])

@php
    $data = app(\App\Content\BlockData::class);
    $validItems = collect(is_array($items) ? $items : [])
        ->filter(fn (mixed $item): bool => is_array($item)
            && is_string($item['label'] ?? null)
            && trim($item['label']) !== ''
            && $data->anchor($item['href'] ?? null) !== null)
        ->values();
@endphp

@if ($validItems->isNotEmpty())
    <nav id="{{ $id }}" class="toc-panel" aria-labelledby="{{ $id }}-title">
        @if (filled($eyebrow))
            <p class="eyebrow text-accent">{{ $eyebrow }}</p>
        @endif
        <h2 id="{{ $id }}-title">{{ $heading }}</h2>
        <ol>
            @foreach ($validItems as $item)
                <li><a href="{{ $item['href'] }}">{{ $item['label'] }}</a></li>
            @endforeach
        </ol>
    </nav>
@endif
