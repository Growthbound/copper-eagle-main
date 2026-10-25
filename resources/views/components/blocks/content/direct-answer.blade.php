@props([
    'id' => 'direct-answer',
    'eyebrow' => null,
    'heading',
    'answer' => [],
    'supporting' => [],
    'source' => null,
    'nextStep' => null,
    'media' => [],
    'mediaSide' => 'start',
])

@php
    $data = app(\App\Content\BlockData::class);
    $richContent = app(\App\Content\RichContent::class);
    // A long answer with nothing beside it reads as a wall. Up to three images
    // sit alongside it as a staggered collage; fewer is fine, none is fine.
    $collage = collect(is_array($media) ? $media : [])
        ->map(fn (mixed $image): ?array => $data->media($image))
        ->filter()
        ->take(3)
        ->values();
    $answerSegments = $richContent->normalizeSegments($answer);
    $supportingSegments = $richContent->normalizeSegments($supporting);
    $validSource = is_array($source)
        && filled($source['label'] ?? null)
        && $richContent->isSafeUrl($source['href'] ?? null)
            ? $source
            : null;
    $validNextStep = is_array($nextStep)
        && filled($nextStep['label'] ?? null)
        && $richContent->isSafeUrl($nextStep['href'] ?? null)
            ? $nextStep
            : null;
@endphp

@if ($answerSegments !== [])
    <section id="{{ $id }}" @class([
        'direct-answer-section',
        'section-space',
        'has-collage' => $collage->isNotEmpty(),
        'media-end' => $collage->isNotEmpty() && $mediaSide === 'end',
    ]) aria-labelledby="{{ $id }}-title">
        @if ($collage->isNotEmpty())
            <div class="direct-answer-collage">
                @foreach ($collage as $image)
                    <img
                        src="{{ $data->assetUrl($image['src']) }}"
                        alt="{{ $image['alt'] }}"
                        width="{{ $image['width'] }}"
                        height="{{ $image['height'] }}"
                        loading="lazy"
                    >
                @endforeach
            </div>
        @endif

        <div class="direct-answer-card">
            @if (filled($eyebrow))
                <p class="eyebrow text-accent">{{ $eyebrow }}</p>
            @endif
            <h2 id="{{ $id }}-title">{{ $heading }}</h2>
            <p class="direct-answer-copy"><x-content.inline-segments :segments="$answerSegments" /></p>

            @if ($supportingSegments !== [])
                <p class="direct-answer-supporting"><x-content.inline-segments :segments="$supportingSegments" /></p>
            @endif

            @if ($validSource || $validNextStep)
                <div class="direct-answer-links">
                    @if ($validSource)
                        <a href="{{ $validSource['href'] }}" @if (preg_match('#^https?://#i', $validSource['href']) === 1) rel="noopener noreferrer" @endif>{{ $validSource['label'] }}</a>
                    @endif
                    @if ($validNextStep)
                        <a href="{{ $validNextStep['href'] }}">{{ $validNextStep['label'] }} <span aria-hidden="true">→</span></a>
                    @endif
                </div>
            @endif
        </div>
    </section>
@endif
