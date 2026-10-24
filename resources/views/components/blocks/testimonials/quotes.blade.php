{{--
    Customer quotes from content/testimonials, narrowed to the page's service
    or area. These are testimonials the business collected with permission —
    not a filtered view of a public review feed.
--}}
@props([
    'id' => 'testimonials',
    'eyebrow' => null,
    'heading' => 'What customers say',
    'service' => null,
    'area' => null,
    'items' => null,
])

@php
    $quotes = $items ?? app(App\Content\TestimonialRepository::class)
        ->for($service, $area)
        ->map(fn (App\Content\Testimonial $testimonial): array => [
            'quote' => $testimonial->quote(),
            'author' => $testimonial->author(),
            'location' => $testimonial->location(),
            'rating' => $testimonial->rating(),
        ])
        ->all();
@endphp

@if (filled($quotes))
    <section id="{{ $id }}" class="testimonial-section section-space" aria-labelledby="{{ $id }}-title">
        <div class="section-heading">
            @if (filled($eyebrow))
                <p class="eyebrow text-accent">{{ $eyebrow }}</p>
            @endif
            <h2 id="{{ $id }}-title">{{ $heading }}</h2>
        </div>

        <div class="testimonial-grid">
            @foreach ($quotes as $quote)
                <figure class="testimonial-card">
                    @if ($quote['rating'] ?? null)
                        <p class="testimonial-rating" aria-label="{{ $quote['rating'] }} out of 5">
                            {{ str_repeat('★', $quote['rating']) }}
                        </p>
                    @endif

                    <blockquote>{{ $quote['quote'] }}</blockquote>

                    <figcaption>
                        {{ $quote['author'] }}@if ($quote['location'] ?? null)<span> · {{ $quote['location'] }}</span>@endif
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </section>
@endif
