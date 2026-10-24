@props([
    'id' => 'inline-cta',
    'eyebrow' => null,
    'heading',
    'body' => null,
    'primaryAction',
    'secondaryAction' => null,
])

@php
    $data = app(\App\Content\BlockData::class);
    $primary = $data->link($primaryAction);
    $secondary = $data->link($secondaryAction);
@endphp

@if ($primary)
    <section id="{{ $id }}" class="inline-cta edge-panel" aria-labelledby="{{ $id }}-title">
        <div>
            @if (filled($eyebrow))
                <p class="eyebrow">{{ $eyebrow }}</p>
            @endif
            <h2 id="{{ $id }}-title">{{ $heading }}</h2>
            @if (filled($body))
                <p>{{ $body }}</p>
            @endif
        </div>
        <div class="inline-cta-actions">
            <a class="button button-orange" href="{{ $primary['href'] }}">{{ $primary['label'] }} <span aria-hidden="true">→</span></a>

            @if ($secondary || filled(config('site.phone')))
                <div class="inline-cta-links">
                    @if ($secondary)
                        <a href="{{ $secondary['href'] }}">{{ $secondary['label'] }}</a>
                    @endif
                    <x-call-link class="cta-call" prefix="or call" />
                </div>
            @endif
        </div>
    </section>
@endif
