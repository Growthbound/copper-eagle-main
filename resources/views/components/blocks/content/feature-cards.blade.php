{{--
    Two to six round photo cards, each with an H3 and a body where a blank
    line starts a new paragraph ("Shape", "Components" and "Page templates"
    in docs/brand/design-system.md).

    `layout` "side" puts a large H2 on the left, over the eagle watermark
    unless `watermark` is false, with the cards to its right (home page).
    "stacked" centres the H2 above a row of cards with copper titles (service
    and about pages). `emphasis` must be an exact substring of `heading` and
    renders in the copper accent tone.

    A card without a title, body, and valid image is left out; with fewer than
    two cards left the section renders nothing.
--}}
@props([
    'id' => 'features',
    'eyebrow' => null,
    'heading',
    'emphasis' => null,
    'body' => null,
    'items' => [],
    'layout' => 'side',
    'background' => 'white',
    'watermark' => true,
])

@php
    $data = app(\App\Content\BlockData::class);
    $layout = in_array($layout, ['side', 'stacked'], true) ? $layout : 'side';
    $background = in_array($background, ['white', 'stone'], true) ? $background : 'white';

    $cards = collect(is_array($items) ? $items : [])
        ->map(function (mixed $item) use ($data): ?array {
            if (! is_array($item)
                || ! is_string($item['title'] ?? null) || trim($item['title']) === ''
                || ! is_string($item['body'] ?? null) || trim($item['body']) === '') {
                return null;
            }

            $image = $data->media($item['image'] ?? null);

            return $image === null ? null : [
                'title' => trim($item['title']),
                'body' => trim($item['body']),
                'image' => $image,
            ];
        })
        ->filter()
        ->take(6)
        ->values();
@endphp

@if ($cards->count() >= 2)
    <section
        id="{{ $id }}"
        @class([
            'feature-cards',
            'edge-panel',
            'feature-cards--'.$layout,
            'feature-cards--'.$background,
            'has-watermark' => $layout === 'side' && $watermark !== false,
        ])
        aria-labelledby="{{ $id }}-title"
    >
        <div class="feature-cards-inner">
            <div class="feature-cards-heading">
                @if (filled($eyebrow))
                    <p class="eyebrow text-accent">{{ $eyebrow }}</p>
                @endif
                <h2 id="{{ $id }}-title"><x-content.emphasized :text="$heading" :emphasis="$emphasis" tone="accent" /></h2>
                @if (filled($body))
                    <p>{{ $body }}</p>
                @endif
            </div>

            <ul class="feature-cards-list is-count-{{ $cards->count() }}" role="list">
                @foreach ($cards as $card)
                    <li class="feature-card">
                        <img
                            class="feature-card-image"
                            src="{{ $data->assetUrl($card['image']['src']) }}"
                            alt="{{ $card['image']['alt'] }}"
                            width="{{ $card['image']['width'] }}"
                            height="{{ $card['image']['height'] }}"
                            loading="lazy"
                            decoding="async"
                        >
                        <h3>{{ $card['title'] }}</h3>
                        <x-content.paragraphs :text="$card['body']" />
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
