@props([
    'id' => 'interior-hero',
    'eyebrow' => null,
    'heading',
    'body' => null,
    'primaryAction' => null,
    'secondaryAction' => null,
])

@php
    $data = app(\App\Content\BlockData::class);
    $primary = $data->link($primaryAction);
    $secondary = $data->link($secondaryAction);
@endphp

<section id="{{ $id }}" class="interior-hero edge-panel" aria-labelledby="{{ $id }}-title">
    <x-site-header :cta="$primary" />
    <div class="interior-hero-inner">
        @if (filled($eyebrow))
            <p class="eyebrow text-accent">{{ $eyebrow }}</p>
        @endif
        <h1 id="{{ $id }}-title">{{ $heading }}</h1>
        @if (filled($body))
            <p>{{ $body }}</p>
        @endif
        @if ($primary || $secondary)
            <div class="hero-variant-actions">
                @if ($primary)
                    <a class="button button-teal" href="{{ $primary['href'] }}">{{ $primary['label'] }} <span aria-hidden="true">→</span></a>
                @endif
                @if ($secondary)
                    <a href="{{ $secondary['href'] }}">{{ $secondary['label'] }}</a>
                @endif
                <x-call-link class="hero-call" prefix="or call" />
            </div>
        @endif
    </div>
</section>
