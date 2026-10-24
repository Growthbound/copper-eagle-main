@props([
    'id' => 'process',
    'eyebrow' => null,
    'heading',
    'steps' => null,
])

@php
    $items = $steps ?? app(App\Content\ProcessStepRepository::class)->all()
        ->map(fn (App\Content\ProcessStep $step): array => [
            'number' => $step->number(),
            'title' => $step->name(),
            'body' => $step->summary(),
        ])
        ->all();
@endphp

<section id="{{ $id }}" class="process-section section-space" aria-labelledby="{{ $id }}-title">
    <div class="section-heading left">
        @if (filled($eyebrow))
            <p class="eyebrow text-accent">{{ $eyebrow }}</p>
        @endif
        <h2 id="{{ $id }}-title">{{ $heading }}</h2>
    </div>

    <div class="process-grid">
        @foreach ($items as $step)
            <article class="process-card">
                <strong>{{ $step['number'] }}</strong>
                <h3>{{ $step['title'] }}</h3>
                <p>{{ $step['body'] }}</p>
            </article>
        @endforeach
    </div>
</section>
