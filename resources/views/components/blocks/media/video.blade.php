@props([
    'id' => 'video',
    'eyebrow' => null,
    'heading',
    'video',
    'poster',
    'transcript',
    'caption' => null,
])

@php
    $data = app(\App\Content\BlockData::class);
    $richContent = app(\App\Content\RichContent::class);
    $validVideo = is_array($video)
        && $richContent->isSafeMediaUrl($video['src'] ?? null)
        && in_array($video['type'] ?? null, ['video/mp4', 'video/webm', 'video/ogg'], true)
            ? $video
            : null;
    $validPoster = $data->media($poster);
@endphp

@if ($validVideo && $validPoster && filled($transcript))
    <section id="{{ $id }}" class="video-block section-space" aria-labelledby="{{ $id }}-title">
        <header class="evidence-section-heading">
            @if (filled($eyebrow))
                <p class="eyebrow text-accent">{{ $eyebrow }}</p>
            @endif
            <h2 id="{{ $id }}-title">{{ $heading }}</h2>
        </header>
        <video controls preload="metadata" poster="{{ $data->assetUrl($validPoster['src']) }}">
            <source src="{{ $data->assetUrl($validVideo['src']) }}" type="{{ $validVideo['type'] }}">
            Your browser does not support embedded video.
        </video>
        @if (filled($caption))
            <p class="video-block-caption">{{ $caption }}</p>
        @endif
        <details>
            <summary>Read the transcript</summary>
            <p>{{ $transcript }}</p>
        </details>
    </section>
@endif
