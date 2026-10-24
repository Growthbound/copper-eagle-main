@props([
    'id' => 'pricing',
    'eyebrow' => null,
    'heading' => null,
    'body' => null,
    'defaultMode' => 'monthly',
    'modes' => [
        'monthly' => ['label' => 'Monthly', 'badge' => null],
        'yearly' => ['label' => 'Yearly', 'badge' => null],
    ],
    'plans' => [],
])

@php
    if (! in_array($defaultMode, ['monthly', 'yearly'], true)) {
        throw new InvalidArgumentException('defaultMode must be monthly or yearly');
    }

    foreach ($plans as $index => $plan) {
        foreach (['monthly', 'yearly'] as $mode) {
            if (! isset($plan['prices'][$mode]['amount'])) {
                throw new InvalidArgumentException(
                    sprintf('Pricing plan %d needs a %s amount for the toggle variant.', $index + 1, $mode),
                );
            }
        }
    }
@endphp

@if ($plans !== [])
    <section
        id="{{ $id }}"
        class="pricing-sheet section-space"
        data-pricing-sheet="toggle"
        @if (filled($heading)) aria-labelledby="{{ $id }}-title" @else aria-label="Pricing options" @endif
    >
        @if (filled($eyebrow) || filled($heading) || filled($body))
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
            </header>
        @endif

        <fieldset class="pricing-mode-switch" data-pricing-modes>
            <legend class="sr-only">Choose a pricing period</legend>

            @foreach (['monthly', 'yearly'] as $mode)
                <label class="pricing-mode-option">
                    <input
                        class="pricing-mode-input"
                        type="radio"
                        name="{{ $id }}-mode"
                        value="{{ $mode }}"
                        @checked($defaultMode === $mode)
                    >
                    <span>{{ $modes[$mode]['label'] ?? ucfirst($mode) }}</span>
                    @if (filled($modes[$mode]['badge'] ?? null))
                        <small>{{ $modes[$mode]['badge'] }}</small>
                    @endif
                </label>
            @endforeach
        </fieldset>

        @include('components.blocks.pricing.partials.grid', [
            'plans' => $plans,
            'modes' => ['monthly', 'yearly'],
        ])
    </section>
@endif
