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
@endphp

@if (filled($author) && $publishedDate)
    <x-site-header :cta="null" />
    <header id="{{ $id }}" class="article-hero" aria-labelledby="{{ $id }}-title">
        <p class="eyebrow text-accent">{{ $eyebrow }}</p>
        <h1 id="{{ $id }}-title">{{ $heading }}</h1>
        @if (filled($summary))
            <p class="article-hero-summary">{{ $summary }}</p>
        @endif
        <div class="article-hero-meta">
            <span>Written by <strong>{{ $author }}</strong></span>
            <time datetime="{{ $publishedDate }}">Published {{ $publishedDate }}</time>
            @if ($updatedDate)
                <time datetime="{{ $updatedDate }}">Updated {{ $updatedDate }}</time>
            @endif
            @if ($validReviewer)
                <span>Reviewed by <strong>{{ $validReviewer['name'] }}</strong></span>
                <time datetime="{{ $validReviewer['reviewed_at'] }}">Reviewed {{ $validReviewer['reviewed_at'] }}</time>
            @endif
        </div>
    </header>
@endif
