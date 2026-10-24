@props([
    'id' => 'quote',
    'eyebrow' => null,
    'heading',
    'body' => null,
    'image' => null,
    'submitLabel' => 'Request a visit',
    'showMessage' => false,
    'purpose' => 'inquiry',
])

@php
    $data = app(\App\Content\BlockData::class);
@endphp

@php
    $hasImage = is_array($image)
        && filled($image['src'] ?? null)
        && filled($image['alt'] ?? null)
        && isset($image['width'], $image['height'])
        && (int) $image['width'] > 0
        && (int) $image['height'] > 0;
    $isTemplatePreview = request()->routeIs('template-preview.*', 'content.preview');
@endphp

<section data-website-form-section
    id="{{ $id }}"
    @class([
        'contact-split-panel',
        'edge-panel',
        'section-space',
        'is-without-media' => ! $hasImage,
    ])
    data-contact-form-variant="split"
    aria-labelledby="{{ $id }}-title"
>
    <div class="contact-split-copy">
        @if (filled($eyebrow))
            <p class="eyebrow">{{ $eyebrow }}</p>
        @endif

        <h2 id="{{ $id }}-title">{{ $heading }}</h2>

        @if (filled($body))
            <p>{{ $body }}</p>
        @endif

        <x-forms.website
            :purpose="$purpose"
            variant="split"
            :show-message="$showMessage"
            :submit-label="$submitLabel"
            :instance-id="$id.'-form'"
            :preview="$isTemplatePreview"
        />
    </div>

    @if ($hasImage)
        <div class="contact-split-media">
            <img
                src="{{ $data->assetUrl($image['src']) }}"
                alt="{{ $image['alt'] }}"
                width="{{ (int) $image['width'] }}"
                height="{{ (int) $image['height'] }}"
                loading="lazy"
            >
        </div>
    @endif
</section>
