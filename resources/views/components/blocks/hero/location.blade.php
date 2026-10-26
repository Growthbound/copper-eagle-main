@props([
    'id' => 'location-hero',
    'eyebrow' => null,
    'heading',
    'place',
    'body' => null,
    'primaryAction',
    'secondaryAction' => null,
])

@php
    $data = app(\App\Content\BlockData::class);
    $validPlace = $data->serviceAreaName($place);
    $primary = $data->link($primaryAction);
    $secondary = $data->link($secondaryAction);
@endphp

@if ($validPlace)
    <x-site-header :cta="$primary" />

    {{-- Shares the interior hero look, so a brand that styles .interior-hero styles this too. --}}
    <section id="{{ $id }}" class="location-hero interior-hero edge-panel" aria-labelledby="{{ $id }}-title">
        <div class="location-hero-inner interior-hero-inner">
            <p class="eyebrow">{{ $eyebrow ?: 'Serving '.$validPlace.' and nearby communities' }}</p>
            <h1 id="{{ $id }}-title">{{ $heading }}</h1>
            @if (filled($body))
                <p>{{ $body }}</p>
            @endif
            @if ($primary || $secondary)
                <div class="hero-variant-actions">
                    @if ($primary)
                        <a class="button button-orange" href="{{ $primary['href'] }}">{{ $primary['label'] }} <span aria-hidden="true">→</span></a>
                    @endif
                    @if ($secondary)
                        <a href="{{ $secondary['href'] }}">{{ $secondary['label'] }}</a>
                    @endif
                </div>
            @endif
        </div>
    </section>
@endif
