@props([
    'id' => 'sources-reviewer',
    'eyebrow' => null,
    'heading',
    'sources' => [],
    'reviewer' => null,
])

@php
    $data = app(\App\Content\BlockData::class);
    $validSources = collect(is_array($sources) ? $sources : [])
        ->filter(fn (mixed $source): bool => is_array($source)
            && is_string($source['label'] ?? null)
            && trim($source['label']) !== ''
            && $data->date($source['reviewed_at'] ?? null) !== null
            && $data->link(['label' => $source['label'] ?? null, 'href' => $source['url'] ?? null]) !== null)
        ->values();
    $validReviewer = is_array($reviewer)
        && is_string($reviewer['name'] ?? null)
        && trim($reviewer['name']) !== ''
        && is_string($reviewer['role'] ?? null)
        && trim($reviewer['role']) !== ''
        && $data->date($reviewer['reviewed_at'] ?? null) !== null
            ? $reviewer
            : null;
@endphp

@if ($validSources->isNotEmpty() || $validReviewer)
    <section id="{{ $id }}" class="sources-reviewer section-space" aria-labelledby="{{ $id }}-title">
        <header class="evidence-section-heading">
            @if (filled($eyebrow))
                <p class="eyebrow text-accent">{{ $eyebrow }}</p>
            @endif
            <h2 id="{{ $id }}-title">{{ $heading }}</h2>
        </header>
        <div class="sources-reviewer-grid">
            @if ($validSources->isNotEmpty())
                <div>
                    <h3>Sources</h3>
                    <ul>
                        @foreach ($validSources as $source)
                            <li>
                                <a href="{{ $source['url'] }}" rel="noopener noreferrer">{{ $source['label'] }}</a>
                                @if (filled($source['publisher'] ?? null))
                                    <span>{{ $source['publisher'] }}</span>
                                @endif
                                <time datetime="{{ $source['reviewed_at'] }}">Reviewed {{ $source['reviewed_at'] }}</time>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if ($validReviewer)
                <div class="reviewer-card">
                    <p class="reviewer-card-label">Reviewed by</p>
                    <strong>{{ $validReviewer['name'] }}</strong>
                    <span>{{ $validReviewer['role'] }}</span>
                    <time datetime="{{ $validReviewer['reviewed_at'] }}">Reviewed {{ $validReviewer['reviewed_at'] }}</time>
                </div>
            @endif
        </div>
    </section>
@endif
