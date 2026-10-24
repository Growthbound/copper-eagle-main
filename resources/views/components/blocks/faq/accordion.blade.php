{{--
    Built with <details> and <summary> rather than JavaScript: keyboard
    accessible for free, works without JS, and the answers are present in the
    markup so search engines and answer engines can read them.

    Defaults to the FAQ records, narrowed to the service or area when the page
    is about one, so a page never has to repeat a general question.
--}}
@props([
    'id' => 'faq',
    'eyebrow' => null,
    'heading' => 'Common questions',
    'service' => null,
    'area' => null,
    'items' => null,
])

@php
    $questions = $items ?? app(App\Content\FaqRepository::class)
        ->for($service, $area)
        ->map(fn (App\Content\Faq $faq): array => [
            'question' => $faq->question(),
            'answer' => $faq->answer(),
        ])
        ->all();
@endphp

@if (filled($questions))
    <section id="{{ $id }}" class="faq-section section-space" aria-labelledby="{{ $id }}-title">
        <div class="section-heading">
            @if (filled($eyebrow))
                <p class="eyebrow text-accent">{{ $eyebrow }}</p>
            @endif
            <h2 id="{{ $id }}-title">{{ $heading }}</h2>
        </div>

        <div class="faq-list">
            @foreach ($questions as $question)
                <details class="faq-item">
                    <summary>{{ $question['question'] }}</summary>
                    <p>{{ $question['answer'] }}</p>
                </details>
            @endforeach
        </div>
    </section>
@endif
