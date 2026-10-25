{{--
    A centered prompt between sections: large heading (one phrase may be
    underlined through `emphasis`), optional body, and one button. White or
    mist band; copper or light-blue button.

    An optional `image` (such as a portrait) sits on the start side with the
    prompt beside it; on phones it stacks above. It comes last in the markup
    so the heading is read first.
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
    'image' => null,
])

@php
    $data = app(\App\Content\BlockData::class);
    $primary = $data->link($primaryAction);
    $secondary = $data->link($secondaryAction);
    $isMist = $background === 'mist';
    $buttonClass = $buttonStyle === 'sky' ? 'button-sky' : 'button-orange';
    $photo = $data->media($image);
@endphp

@if ($primary)
    <section
        id="{{ $id }}"
        @class(['inline-cta', 'edge-panel', 'is-mist' => $isMist, 'has-media' => $photo !== null])
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

            {{-- No "or call" line when the button already places the call. --}}
            @php($primaryCalls = str_starts_with((string) $primary['href'], 'tel:'))
            @if ($secondary || (filled(config('site.phone')) && ! $primaryCalls))
                <div class="inline-cta-links">
                    @if ($secondary)
                        <a href="{{ $secondary['href'] }}">{{ $secondary['label'] }}</a>
                    @endif
                    @unless ($primaryCalls)
                        <x-call-link class="cta-call" prefix="or call" />
                    @endunless
                </div>
            @endif
        </div>

        @if ($photo)
            <div class="inline-cta-media">
                <img
                    src="{{ $data->assetUrl($photo['src']) }}"
                    alt="{{ $photo['alt'] }}"
                    width="{{ $photo['width'] }}"
                    height="{{ $photo['height'] }}"
                    loading="lazy"
                >
            </div>
        @endif
    </section>
@endif
