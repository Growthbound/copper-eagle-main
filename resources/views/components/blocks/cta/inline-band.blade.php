{{--
    A centered prompt between sections: large heading (one phrase may be
    underlined through `emphasis`), optional body, and one button. White or
    mist band; copper or light-blue button.
--}}
@props([
    'id' => 'inline-cta',
    'eyebrow' => null,
    'heading',
    'emphasis' => null,
    'body' => null,
    'primaryAction',
    'secondaryAction' => null,
    'background' => 'white',
    'buttonStyle' => 'copper',
])

@php
    $data = app(\App\Content\BlockData::class);
    $primary = $data->link($primaryAction);
    $secondary = $data->link($secondaryAction);
    $isMist = $background === 'mist';
    $buttonClass = $buttonStyle === 'sky' ? 'button-sky' : 'button-orange';
@endphp

@if ($primary)
    <section
        id="{{ $id }}"
        @class(['inline-cta', 'edge-panel', 'is-mist' => $isMist])
        aria-labelledby="{{ $id }}-title"
    >
        <div>
            @if (filled($eyebrow))
                <p class="eyebrow">{{ $eyebrow }}</p>
            @endif
            <h2 id="{{ $id }}-title"><x-content.emphasized :text="$heading" :emphasis="$emphasis" /></h2>
            @if (filled($body))
                <p>{{ $body }}</p>
            @endif
        </div>
        <div class="inline-cta-actions">
            <a class="button {{ $buttonClass }}" href="{{ $primary['href'] }}">{{ $primary['label'] }}</a>

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
