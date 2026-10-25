@props([
    'id' => 'scope',
    'eyebrow' => null,
    'heading',
    'included' => [],
    'excluded' => [],
])

@php
    $data = app(\App\Content\BlockData::class);
    $includedItems = $data->strings($included);
    $excludedItems = $data->strings($excluded);
@endphp

@if ($includedItems !== [] || $excludedItems !== [])
    <section id="{{ $id }}" class="scope-section section-space" aria-labelledby="{{ $id }}-title">
        <header class="evidence-section-heading">
            @if (filled($eyebrow))
                <p class="eyebrow text-accent">{{ $eyebrow }}</p>
            @endif
            <h2 id="{{ $id }}-title">{{ $heading }}</h2>
        </header>
        <div class="scope-grid">
            <article class="scope-card scope-card--included">
                <h3>Included</h3>
                <ul>
                    @foreach ($includedItems as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </article>
            <article class="scope-card scope-card--excluded">
                <h3>Not included</h3>
                <ul>
                    @foreach ($excludedItems as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </article>
        </div>
    </section>
@endif
