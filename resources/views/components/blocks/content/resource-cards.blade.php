@props([
    'id' => 'resources',
    'eyebrow' => null,
    'heading',
    'summaryLabel' => 'Read quick tip',
    'items' => null,
])

@php
    $data = app(\App\Content\BlockData::class);
@endphp

@php
    $cards = $items ?? app(App\Content\ResourceRepository::class)->all()
        ->map(fn (App\Content\Resource $resource): array => [
            'title' => $resource->name(),
            'body' => $resource->summary(),
            'image' => $resource->image(),
        ])
        ->all();
@endphp

<section id="{{ $id }}" class="resources-section section-space" aria-labelledby="{{ $id }}-title">
    <div class="section-heading">
        @if (filled($eyebrow))
            <p class="eyebrow text-accent">{{ $eyebrow }}</p>
        @endif
        <h2 id="{{ $id }}-title">{{ $heading }}</h2>
    </div>

    <div class="resource-grid">
        @foreach ($cards as $card)
            <article class="resource-card">
                @if (filled($card['image'] ?? null))
                    <img
                        src="{{ $data->assetUrl($card['image']['src']) }}"
                        alt="{{ $card['image']['alt'] }}"
                        width="{{ $card['image']['width'] }}"
                        height="{{ $card['image']['height'] }}"
                        loading="lazy"
                    >
                @endif
                <div class="glass-card">
                    <h3>{{ $card['title'] }}</h3>
                    <details>
                        <summary>{{ $summaryLabel }}</summary>
                        <p>{{ $card['body'] }}</p>
                    </details>
                    @if (filled($card['href'] ?? null))
                        <a
                            class="text-link"
                            href="{{ $card['href'] }}"
                            @if (str_starts_with($card['href'], 'http')) rel="noopener noreferrer" @endif
                        >
                            Read {{ $card['title'] }} <span aria-hidden="true">→</span>
                        </a>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</section>
