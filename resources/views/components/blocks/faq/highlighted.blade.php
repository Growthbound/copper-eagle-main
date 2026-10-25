@php
    /*
     * Blade applies prop defaults with null coalescing, which would turn an
     * explicitly bound null back into the default zero. Capture the raw value
     * first so authors can intentionally start with every item closed.
     */
    $rawOpenIndexProvided = array_key_exists('open-index', $attributes->all());
    $rawOpenIndex = $rawOpenIndexProvided ? $attributes->all()['open-index'] : null;
@endphp

@props([
    'id' => 'faq-highlighted',
    'eyebrow' => null,
    'heading' => 'Common questions',
    'body' => null,
    'service' => null,
    'area' => null,
    'items' => null,
    'openIndex' => 0,
])

@php
    if ($rawOpenIndexProvided) {
        $openIndex = $rawOpenIndex;
    }

    $questions = $items ?? app(App\Content\FaqRepository::class)
        ->for($service, $area)
        ->map(fn (App\Content\Faq $faq): array => [
            'question' => $faq->question(),
            'answer' => $faq->answer(),
        ])
        ->all();
@endphp

@if (filled($questions))
    <section
        id="{{ $id }}"
        class="faq-highlighted section-space"
        data-faq-variant="highlighted"
        aria-labelledby="{{ $id }}-title"
    >
        <header class="faq-highlighted-heading">
            @if (filled($eyebrow))
                <p class="eyebrow text-accent">{{ $eyebrow }}</p>
            @endif
            <h2 id="{{ $id }}-title">{{ $heading }}</h2>
            @if (filled($body))
                <p class="faq-highlighted-intro">{{ $body }}</p>
            @endif
        </header>

        <div class="faq-highlighted-list">
            @foreach ($questions as $question)
                <details
                    class="faq-highlighted-item"
                    name="{{ $id }}-group"
                    @if ($openIndex !== null && (int) $openIndex === $loop->index) open @endif
                >
                    <summary>
                        <span class="faq-highlighted-arrow" aria-hidden="true"></span>
                        <span>{{ $question['question'] }}</span>
                    </summary>
                    <p>{{ $question['answer'] }}</p>
                </details>
            @endforeach
        </div>
    </section>
@endif
