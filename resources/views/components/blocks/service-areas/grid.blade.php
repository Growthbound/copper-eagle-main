@props([
    'id' => 'service-area-grid',
    'eyebrow' => null,
    'heading',
    'areas' => [],
])

@php
    $validAreas = app(\App\Content\BlockData::class)->serviceAreas($areas);
@endphp

@if ($validAreas !== [])
    <section id="{{ $id }}" class="service-area-grid-section section-space" aria-labelledby="{{ $id }}-title">
        <header class="local-section-heading">
            @if (filled($eyebrow))
                <p class="eyebrow text-accent">{{ $eyebrow }}</p>
            @endif
            <h2 id="{{ $id }}-title">{{ $heading }}</h2>
        </header>
        <ul class="service-area-card-grid">
            @foreach ($validAreas as $area)
                <li>
                    <a href="{{ $area['href'] }}">
                        <span>{{ $area['name'] }}</span>
                        <span aria-hidden="true">→</span>
                    </a>
                    @if (filled($area['note'] ?? null))
                        <p>{{ $area['note'] }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endif
