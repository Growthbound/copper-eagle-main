@props([
    'id' => 'related-content',
    'eyebrow' => null,
    'heading',
    'items' => [],
])

@php
    $data = app(\App\Content\BlockData::class);
    $validItems = collect(is_array($items) ? $items : [])
        ->filter(fn (mixed $item): bool => is_array($item)
            && is_string($item['title'] ?? null)
            && trim($item['title']) !== ''
            && $data->link(['label' => $item['title'] ?? null, 'href' => $item['href'] ?? null]) !== null)
        ->values();
@endphp

@if ($validItems->isNotEmpty())
    <section id="{{ $id }}" class="related-content-section section-space" aria-labelledby="{{ $id }}-title">
        <header class="evidence-section-heading">
            @if (filled($eyebrow))
                <p class="eyebrow text-accent">{{ $eyebrow }}</p>
            @endif
            <h2 id="{{ $id }}-title">{{ $heading }}</h2>
        </header>
        <div class="related-content-grid">
            @foreach ($validItems as $item)
                <article>
                    @if (filled($item['type'] ?? null))
                        <p class="eyebrow">{{ $item['type'] }}</p>
                    @endif
                    <h3><a href="{{ $item['href'] }}">{{ $item['title'] }}</a></h3>
                    @if (filled($item['summary'] ?? null))
                        <p>{{ $item['summary'] }}</p>
                    @endif

                    {{--
                        Decorative: the heading is already the link, and the
                        card is made clickable in CSS. Announcing a second link
                        to the same place would only add noise for a screen
                        reader.
                    --}}
                    <span class="related-card-view" aria-hidden="true">View <span>→</span></span>
                </article>
            @endforeach
        </div>
    </section>
@endif
