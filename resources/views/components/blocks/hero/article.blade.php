@props([
    'id' => 'article-hero',
    'eyebrow' => 'Helpful guide',
    'heading',
    'summary' => null,
    'author',
    'publishedAt',
    'updatedAt' => null,
    'reviewer' => null,
])

@php
    $data = app(\App\Content\BlockData::class);
    $publishedDate = $data->date($publishedAt);
    // Shown only when it differs, so an article that has never been revised
    // does not carry two identical dates.
    $updatedDate = $data->date($updatedAt);
    $updatedDate = $updatedDate && $updatedDate !== $publishedDate ? $updatedDate : null;
    $validReviewer = is_array($reviewer)
        && is_string($reviewer['name'] ?? null)
        && trim($reviewer['name']) !== ''
        && $data->date($reviewer['reviewed_at'] ?? null)
            ? $reviewer
            : null;
    // Dates read the way the live blog writes them ("Sep 8, 2023"); the
    // machine-readable ISO date stays in each <time datetime>.
    $readable = static fn (string $date): string => \Carbon\CarbonImmutable::createFromFormat('!Y-m-d', $date)->format('M j, Y');
@endphp

@if (filled($author) && $publishedDate)
    <x-site-header :cta="null" />
    <header id="{{ $id }}" class="article-hero" aria-labelledby="{{ $id }}-title">
        @if (filled($eyebrow))
            <p class="eyebrow text-accent">{{ $eyebrow }}</p>
        @endif
        <h1 id="{{ $id }}-title">{{ $heading }}</h1>
        @if (filled($summary))
            <p class="article-hero-summary">{{ $summary }}</p>
        @endif
        <div class="article-hero-meta">
            <time datetime="{{ $publishedDate }}">{{ $readable($publishedDate) }}</time>
            <span>Written by <strong>{{ $author }}</strong></span>
            @if ($updatedDate)
                <time datetime="{{ $updatedDate }}">Updated {{ $readable($updatedDate) }}</time>
            @endif
            @if ($validReviewer)
                <span>Reviewed by <strong>{{ $validReviewer['name'] }}</strong></span>
                <time datetime="{{ $validReviewer['reviewed_at'] }}">Reviewed {{ $readable($validReviewer['reviewed_at']) }}</time>
            @endif
        </div>
    </header>
@endif
