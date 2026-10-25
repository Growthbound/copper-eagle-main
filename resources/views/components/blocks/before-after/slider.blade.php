@props([
    'id' => 'before-after',
    'eyebrow' => null,
    'heading',
    'before',
    'after',
])

@php
    $data = app(\App\Content\BlockData::class);
    $beforeImage = $data->media($before, true);
    $afterImage = $data->media($after, true);
@endphp

@if ($beforeImage && $afterImage)
    <section id="{{ $id }}" class="before-after-section section-space" aria-labelledby="{{ $id }}-title" data-before-after>
        <header class="evidence-section-heading">
            @if (filled($eyebrow))
                <p class="eyebrow text-accent">{{ $eyebrow }}</p>
            @endif
            <h2 id="{{ $id }}-title">{{ $heading }}</h2>
        </header>
        <div class="before-after-stage">
            <figure class="before-after-image before-after-image--before">
                <img src="{{ $data->assetUrl($beforeImage['src']) }}" alt="{{ $beforeImage['alt'] }}" width="{{ $beforeImage['width'] }}" height="{{ $beforeImage['height'] }}" loading="lazy">
                <figcaption>Before</figcaption>
            </figure>
            <figure class="before-after-image before-after-image--after">
                <img src="{{ $data->assetUrl($afterImage['src']) }}" alt="{{ $afterImage['alt'] }}" width="{{ $afterImage['width'] }}" height="{{ $afterImage['height'] }}" loading="lazy">
                <figcaption>After</figcaption>
            </figure>
            <div class="before-after-divider" aria-hidden="true"></div>
        </div>
        <label class="before-after-control">
            <span>Move comparison</span>
            <input type="range" min="0" max="100" value="50" data-before-after-control>
        </label>
        <div class="evidence-sources">
            <a href="{{ $beforeImage['source_url'] }}" rel="noopener noreferrer">Before: {{ $beforeImage['source_label'] }}</a>
            <a href="{{ $afterImage['source_url'] }}" rel="noopener noreferrer">After: {{ $afterImage['source_label'] }}</a>
        </div>
    </section>
@endif
