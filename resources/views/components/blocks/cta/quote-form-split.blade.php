{{--
    The form on a mist card beside a photo. `showContact` adds the phone
    number (a call button under the heading) and the email address (under the
    body), both from site settings and each hidden when blank. An optional
    `secondaryImage` stacks under the first photo.
--}}
@props([
    'id' => 'quote',
    'eyebrow' => null,
    'heading',
    'body' => null,
    'image' => null,
    'submitLabel' => 'Request a visit',
    'showMessage' => false,
    'purpose' => 'inquiry',
    'showContact' => false,
    'secondaryImage' => null,
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
    $second = $hasImage ? $data->media($secondaryImage) : null;
    $email = $showContact === true ? trim((string) config('site.email')) : '';
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

        @if ($showContact === true)
            <x-call-link class="button button-orange contact-split-call" />
        @endif

        @if (filled($body))
            <p>{{ $body }}</p>
        @endif

        @if (filter_var($email, FILTER_VALIDATE_EMAIL))
            <p class="contact-split-email"><a href="mailto:{{ $email }}">{{ $email }}</a></p>
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
        <div @class(['contact-split-media', 'has-secondary' => $second !== null])>
            <img
                src="{{ $data->assetUrl($image['src']) }}"
                alt="{{ $image['alt'] }}"
                width="{{ (int) $image['width'] }}"
                height="{{ (int) $image['height'] }}"
                loading="lazy"
            >
            @if ($second)
                <img
                    class="contact-split-secondary"
                    src="{{ $data->assetUrl($second['src']) }}"
                    alt="{{ $second['alt'] }}"
                    width="{{ $second['width'] }}"
                    height="{{ $second['height'] }}"
                    loading="lazy"
                >
            @endif
        </div>
    @endif
</section>
