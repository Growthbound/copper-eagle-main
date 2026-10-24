@props([
    'id' => 'process-icon-grid',
    'eyebrow' => null,
    'heading' => 'How it works',
    'body' => null,
    'steps' => null,
])

@php
    $fallbackIcons = ['magnifying-glass', 'sparkles', 'check-circle'];

    $items = $steps ?? app(App\Content\ProcessStepRepository::class)->all()
        ->values()
        ->map(fn (App\Content\ProcessStep $step, int $index): array => [
            'icon' => (string) $step->get('icon', $fallbackIcons[$index % count($fallbackIcons)]),
            'title' => $step->name(),
            'body' => $step->summary(),
        ])
        ->all();
@endphp

@if (filled($items))
    <section
        id="{{ $id }}"
        class="process-icon-grid-section section-space"
        data-process-variant="icon-grid"
        aria-labelledby="{{ $id }}-title"
    >
        <header class="process-icon-grid-heading">
            @if (filled($eyebrow))
                <p class="eyebrow text-accent">{{ $eyebrow }}</p>
            @endif
            <h2 id="{{ $id }}-title">{{ $heading }}</h2>
            @if (filled($body))
                <p class="process-icon-grid-intro">{{ $body }}</p>
            @endif
        </header>

        <div class="process-icon-grid">
            @foreach ($items as $step)
                <article
                    class="process-icon-grid-card"
                    data-step-icon="{{ $step['icon'] }}"
                >
                    <x-marketing-icon
                        :name="$step['icon']"
                        class="process-icon-grid-symbol"
                    />
                    <h3>{{ $step['title'] }}</h3>
                    <p>{{ $step['body'] }}</p>
                </article>
            @endforeach
        </div>
    </section>
@endif
