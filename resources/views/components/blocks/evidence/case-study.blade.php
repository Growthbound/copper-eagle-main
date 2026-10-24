@props([
    'id' => 'case-study',
    'eyebrow' => null,
    'heading',
    'problem',
    'work',
    'outcome',
    'facts' => [],
    'image' => null,
])

@php
    $data = app(\App\Content\BlockData::class);
    $validFacts = collect(is_array($facts) ? $facts : [])
        ->filter(fn (mixed $fact): bool => is_array($fact)
            && is_string($fact['label'] ?? null)
            && trim($fact['label']) !== ''
            && is_string($fact['value'] ?? null)
            && trim($fact['value']) !== ''
            && $data->source($fact) !== null)
        ->values();
    $validImage = $data->media($image);
@endphp

@if (filled($problem) && filled($work) && filled($outcome))
    <section id="{{ $id }}" class="case-study-section section-space" aria-labelledby="{{ $id }}-title">
        <header class="evidence-section-heading">
            @if (filled($eyebrow))
                <p class="eyebrow text-accent">{{ $eyebrow }}</p>
            @endif
            <h2 id="{{ $id }}-title">{{ $heading }}</h2>
        </header>
        @if ($validImage)
            <img class="case-study-image" src="{{ $data->assetUrl($validImage['src']) }}" alt="{{ $validImage['alt'] }}" width="{{ $validImage['width'] }}" height="{{ $validImage['height'] }}" loading="lazy">
        @endif
        <div class="case-study-stages">
            <article><p class="eyebrow">The situation</p><p>{{ $problem }}</p></article>
            <article><p class="eyebrow">What we did</p><p>{{ $work }}</p></article>
            <article><p class="eyebrow">Documented outcome</p><p>{{ $outcome }}</p></article>
        </div>
        @if ($validFacts->isNotEmpty())
            <dl class="case-study-facts">
                @foreach ($validFacts as $fact)
                    <div>
                        <dt>{{ $fact['label'] }}</dt>
                        <dd>{{ $fact['value'] }}</dd>
                        <a href="{{ $fact['source_url'] }}" rel="noopener noreferrer">{{ $fact['source_label'] }}</a>
                    </div>
                @endforeach
            </dl>
        @endif
    </section>
@endif
