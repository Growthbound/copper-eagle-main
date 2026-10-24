@props([
    'id' => 'pricing-factors',
    'eyebrow' => null,
    'heading',
    'factors' => [],
])

@php
    $validFactors = collect(is_array($factors) ? $factors : [])
        ->filter(fn (mixed $factor): bool => is_array($factor)
            && is_string($factor['label'] ?? null)
            && trim($factor['label']) !== ''
            && is_string($factor['description'] ?? null)
            && trim($factor['description']) !== '')
        ->values();
@endphp

@if ($validFactors->isNotEmpty())
    <section id="{{ $id }}" class="pricing-factors-section section-space" aria-labelledby="{{ $id }}-title">
        <header class="evidence-section-heading">
            @if (filled($eyebrow))
                <p class="eyebrow text-accent">{{ $eyebrow }}</p>
            @endif
            <h2 id="{{ $id }}-title">{{ $heading }}</h2>
        </header>
        <ol class="pricing-factors-grid">
            @foreach ($validFactors as $factor)
                <li>
                    <span aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    <h3>{{ $factor['label'] }}</h3>
                    <p>{{ $factor['description'] }}</p>
                </li>
            @endforeach
        </ol>
    </section>
@endif
