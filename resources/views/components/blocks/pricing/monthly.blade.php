@props([
    'id' => 'pricing',
    'eyebrow' => null,
    'heading' => null,
    'body' => null,
    'badge' => null,
    'plans' => [],
])

@php
    $availablePlans = collect($plans)
        ->filter(fn (array $plan): bool => isset($plan['prices']['monthly']['amount']))
        ->values();
@endphp

@if ($availablePlans->isNotEmpty())
    <section
        id="{{ $id }}"
        class="pricing-sheet section-space"
        data-pricing-sheet="monthly"
        @if (filled($heading)) aria-labelledby="{{ $id }}-title" @else aria-label="Pricing options" @endif
    >
        @if (filled($eyebrow) || filled($heading) || filled($body) || filled($badge))
            <header class="pricing-heading">
                @if (filled($eyebrow))
                    <p class="eyebrow text-accent">{{ $eyebrow }}</p>
                @endif
                @if (filled($heading))
                    <h2 id="{{ $id }}-title">{{ $heading }}</h2>
                @endif
                @if (filled($body))
                    <p>{{ $body }}</p>
                @endif
                @if (filled($badge))
                    <span class="pricing-mode-badge">{{ $badge }}</span>
                @endif
            </header>
        @endif

        @include('components.blocks.pricing.partials.grid', [
            'plans' => $availablePlans,
            'modes' => ['monthly'],
        ])
    </section>
@endif
