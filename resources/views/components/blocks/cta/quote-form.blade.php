{{--
    Provider selection is shared with the split layout. Native intake remains
    available for the eventual migration away from HighLevel.
--}}
@props([
    'id' => 'quote',
    'eyebrow' => null,
    'heading',
    'body' => null,
    'purpose' => 'inquiry',
])

<section data-website-form-section id="{{ $id }}" class="quote-panel dotted-panel edge-panel section-space" aria-labelledby="{{ $id }}-title">
    <div class="section-copy text-on-primary">
        @if (filled($eyebrow))
            <p class="eyebrow">{{ $eyebrow }}</p>
        @endif

        <h2 id="{{ $id }}-title">{{ $heading }}</h2>

        @if (filled($body))
            <p>{{ $body }}</p>
        @endif
    </div>

    <x-forms.website
        :purpose="$purpose"
        :instance-id="$id.'-form'"
        :preview="request()->routeIs('template-preview.*', 'content.preview')"
    />
</section>
