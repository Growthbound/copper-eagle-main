@props([
    'id' => 'local-project',
    'eyebrow' => null,
    'heading',
    'place',
    'work',
    'outcome',
    'source',
    'image' => null,
])

@php
    $data = app(\App\Content\BlockData::class);
    $validPlace = $data->serviceAreaName($place);
    $validSource = $data->link($source);
    $validImage = $data->media($image);
@endphp

@if ($validPlace && $validSource && filled($work) && filled($outcome))
    <section id="{{ $id }}" class="local-project-section section-space" aria-labelledby="{{ $id }}-title">
        <div class="local-project-copy">
            <p class="eyebrow text-accent">{{ $eyebrow ?: 'Service-area project' }}</p>
            <h2 id="{{ $id }}-title">{{ $heading }}</h2>
            <p class="local-project-place">{{ $validPlace }}</p>
            <dl>
                <div><dt>Documented work</dt><dd>{{ $work }}</dd></div>
                <div><dt>Documented outcome</dt><dd>{{ $outcome }}</dd></div>
            </dl>
            <a href="{{ $validSource['href'] }}" rel="noopener noreferrer">{{ $validSource['label'] }}</a>
        </div>
        @if ($validImage)
            <img src="{{ $data->assetUrl($validImage['src']) }}" alt="{{ $validImage['alt'] }}" width="{{ $validImage['width'] }}" height="{{ $validImage['height'] }}" loading="lazy">
        @endif
    </section>
@endif
