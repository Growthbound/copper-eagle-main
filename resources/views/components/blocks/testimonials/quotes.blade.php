{{--
    Customer quotes from content/testimonials, narrowed to the page's service
    or area. These are testimonials the business collected with permission —
    not a filtered view of a public review feed.

    Renders as the light-blue band with the diamond lattice: centered heading,
    centered quotes with their names, and an optional outline link to more.
    Text on the band is ink or deep blue, never white.
--}}
@props([
    'id' => 'testimonials',
    'eyebrow' => null,
    'heading' => 'What customers say',
    'service' => null,
    'area' => null,
    'items' => null,
    'link' => null,
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
    $more = app(\App\Content\BlockData::class)->link($link);
@endphp

@if (filled($quotes))
    <section
        id="{{ $id }}"
        @class([
            'testimonial-section',
            'testimonial-band',
            'edge-panel',
            'is-single' => count($quotes) === 1,
        ])
        aria-labelledby="{{ $id }}-title"
    >
        <div class="section-heading">
            @if (filled($eyebrow))
                <p class="eyebrow">{{ $eyebrow }}</p>
            @endif
            <h2 id="{{ $id }}-title">{{ $heading }}</h2>
        </div>

        <div class="testimonial-grid">
            @foreach ($quotes as $quote)
                @php($context = $quote['location'] ?? $quote['role'] ?? null)
                <figure class="testimonial-card">
                    @if ($quote['rating'] ?? null)
                        <p class="testimonial-rating" aria-label="{{ $quote['rating'] }} out of 5">
                            {{ str_repeat('★', $quote['rating']) }}
                        </p>
                    @endif

                    <blockquote>{{ $quote['quote'] }}</blockquote>

                    <figcaption>
                        {{ $quote['author'] }}@if (filled($context))<span> · {{ $context }}</span>@endif
                    </figcaption>
                </figure>
            @endforeach
        </div>

        @if ($more)
            <p class="testimonial-more">
                <a class="button button-outline" href="{{ $more['href'] }}">{{ $more['label'] }}</a>
            </p>
        @endif
    </section>
@endif
