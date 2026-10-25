{{--
    The two-column workhorse section.

    With an image: the photo (square, or a circle portrait with the inset as a
    badge over its lower-left edge) beside the copy. With no image: a large
    statement heading and its button on one side, the detail copy on the other,
    where the eyebrow reads as a copper subheading.

    `background` sets the full-width band behind the section; `imageSide`
    mirrors the columns. Every option falls back to the original look.
--}}
@props([
    'id' => null,
    'eyebrow' => null,
    'heading',
    'emphasis' => null,
    'lead' => null,
    'body' => null,
    'checklist' => [],
    'cta' => null,
    'image' => null,
    'imageShape' => 'rectangle',
    'imageSide' => 'start',
    'inset' => null,
    'stamp' => null,
    'background' => 'white',
])

@php
    $data = app(\App\Content\BlockData::class);
    $titleId = ($id ?? 'split').'-title';
    $action = $data->link($cta);
    $hasImage = filled($image);
    $isCircle = $imageShape === 'circle';
    $imageAtEnd = $imageSide === 'end';
    $band = in_array($background, ['mist', 'stone'], true) ? $background : null;
    $parts = $hasImage && $imageAtEnd ? ['copy', 'media'] : ['media', 'copy'];
    $hasDetail = filled($eyebrow) || filled($lead) || filled($body) || filled($checklist);
@endphp

<section
    @if ($id)id="{{ $id }}" @endif
    @class([
        'split-section',
        'section-space',
        'is-without-media' => ! $hasImage,
        'is-statement-only' => ! $hasImage && ! $hasDetail,
        'is-image-circle' => $hasImage && $isCircle,
        'is-image-end' => $hasImage && $imageAtEnd,
        'split-band' => $band !== null,
        'edge-panel' => $band !== null,
        'split-band--'.$band => $band !== null,
    ])
    aria-labelledby="{{ $titleId }}"
>
    @if ($hasImage)
        @foreach ($parts as $part)
            @if ($part === 'media')
                <div class="image-stack">
                    <img
                        class="image-stack-main"
                        src="{{ $data->assetUrl($image['src']) }}"
                        alt="{{ $image['alt'] }}"
                        width="{{ $image['width'] }}"
                        height="{{ $image['height'] }}"
                        loading="lazy"
                    >

                    @if (filled($inset))
                        <img
                            class="image-stack-small"
                            src="{{ $data->assetUrl($inset['src']) }}"
                            alt="{{ $inset['alt'] }}"
                            width="{{ $inset['width'] }}"
                            height="{{ $inset['height'] }}"
                            loading="lazy"
                        >
                    @endif

                    @if (filled($stamp))
                        <span class="image-stamp" aria-hidden="true">{{ $stamp }}</span>
                    @endif
                </div>
            @else
                <div class="section-copy">
                    @if (filled($eyebrow))
                        <p class="eyebrow text-accent">{{ $eyebrow }}</p>
                    @endif

                    <h2 id="{{ $titleId }}"><x-content.emphasized :text="$heading" :emphasis="$emphasis" /></h2>

                    @if (filled($lead))
                        <p class="split-lead">{{ $lead }}</p>
                    @endif

                    @if (filled($body))
                        <p>{{ $body }}</p>
                    @endif

                    @if (filled($checklist))
                        <ul class="check-list">
                            @foreach ($checklist as $point)
                                <li><x-check-icon class="check-icon-small" />{{ $point }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($action)
                        <a class="button button-orange split-action" href="{{ $action['href'] }}">{{ $action['label'] }}</a>
                    @endif
                </div>
            @endif
        @endforeach
    @else
        <div class="split-statement">
            <h2 id="{{ $titleId }}"><x-content.emphasized :text="$heading" :emphasis="$emphasis" /></h2>

            @if ($action)
                <a class="button button-orange split-action" href="{{ $action['href'] }}">{{ $action['label'] }}</a>
            @endif
        </div>

        @if ($hasDetail)
            <div class="section-copy">
                @if (filled($eyebrow))
                    <p class="split-subheading">{{ $eyebrow }}</p>
                @endif

                @if (filled($lead))
                    <p class="split-lead">{{ $lead }}</p>
                @endif

                @if (filled($body))
                    <p>{{ $body }}</p>
                @endif

                @if (filled($checklist))
                    <ul class="check-list">
                        @foreach ($checklist as $point)
                            <li><x-check-icon class="check-icon-small" />{{ $point }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    @endif
</section>
