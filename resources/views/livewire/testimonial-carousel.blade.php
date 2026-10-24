@php
    $visibleReviews = $this->visibleItems();
@endphp

<section
    id="{{ $carouselId }}"
    class="review-carousel-panel edge-panel dotted-panel section-space"
    data-testimonial-variant="carousel"
    aria-labelledby="{{ $carouselId }}-title"
    aria-roledescription="carousel"
>
    <header class="review-carousel-heading">
        @if (filled($eyebrow))
            <p class="eyebrow text-accent-on-primary">{{ $eyebrow }}</p>
        @endif
        <h2 id="{{ $carouselId }}-title">{{ $heading }}</h2>
    </header>

    <div class="review-carousel-window" aria-live="polite">
        <div class="review-carousel-track">
            @foreach ($visibleReviews as $visibleReview)
                @php
                    $review = $visibleReview['item'];
                    $reviewIndex = $visibleReview['index'];
                    $isActive = $reviewIndex === $active;
                    $isVideo = ($review['type'] ?? 'quote') === 'video';
                    $avatar = $review['avatar'] ?? null;
                    $poster = $review['poster'] ?? null;
                @endphp

                @if ($isVideo)
                    <article
                        class="review-carousel-card review-carousel-card--video"
                        data-card-type="video"
                        data-review-index="{{ $reviewIndex }}"
                        data-active="{{ $isActive ? 'true' : 'false' }}"
                        @if ($isActive) aria-current="true" @endif
                        wire:key="{{ $carouselId }}-review-{{ $reviewIndex }}"
                    >
                        <div class="review-carousel-video-media">
                            @if (
                                is_array($poster)
                                && filled($poster['src'] ?? null)
                                && isset($poster['width'], $poster['height'])
                            )
                                <img
                                    src="{{ $poster['src'] }}"
                                    alt="{{ $poster['alt'] ?? $review['title'] ?? 'Customer video' }}"
                                    width="{{ (int) $poster['width'] }}"
                                    height="{{ (int) $poster['height'] }}"
                                    loading="lazy"
                                >
                            @else
                                <span class="review-carousel-video-placeholder" aria-hidden="true"></span>
                            @endif

                            <span class="review-carousel-video-shade" aria-hidden="true"></span>

                            @if (filled($review['video_url'] ?? null))
                                <a
                                    class="review-carousel-play"
                                    href="{{ $review['video_url'] }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label="Play {{ $review['title'] ?? 'customer video' }}"
                                >
                                    <x-marketing-icon name="play" />
                                </a>
                            @else
                                <span
                                    class="review-carousel-play is-disabled"
                                    data-play-disabled="true"
                                    aria-hidden="true"
                                >
                                    <x-marketing-icon name="play" />
                                </span>
                            @endif

                            <span class="review-carousel-video-copy">
                                @if (filled($review['title']))
                                    <strong>{{ $review['title'] }}</strong>
                                @endif
                                @if (filled($review['context']))
                                    <small>{{ $review['context'] }}</small>
                                @endif
                            </span>
                        </div>
                        @if (filled($review['transcript'] ?? null))
                            <details class="review-carousel-transcript">
                                <summary>Read transcript</summary>
                                <p>{{ $review['transcript'] }}</p>
                            </details>
                        @endif
                    </article>
                @else
                    @php
                        $initials = collect(preg_split('/\s+/', trim($review['author'])) ?: [])
                            ->filter()
                            ->take(2)
                            ->map(fn (string $part): string => Illuminate\Support\Str::upper(
                                Illuminate\Support\Str::substr($part, 0, 1)
                            ))
                            ->implode('');
                    @endphp
                    <figure
                        class="review-carousel-card"
                        data-card-type="quote"
                        data-review-index="{{ $reviewIndex }}"
                        data-active="{{ $isActive ? 'true' : 'false' }}"
                        @if ($isActive) aria-current="true" @endif
                        wire:key="{{ $carouselId }}-review-{{ $reviewIndex }}"
                    >
                        <span class="review-carousel-quote-mark" aria-hidden="true">“</span>

                        @if (filled($review['title']))
                            <h3>{{ $review['title'] }}</h3>
                        @endif

                        <blockquote>{{ $review['quote'] }}</blockquote>

                        <figcaption class="review-carousel-author">
                            @if (
                                is_array($avatar)
                                && filled($avatar['src'] ?? null)
                                && isset($avatar['width'], $avatar['height'])
                            )
                                <img
                                    src="{{ $avatar['src'] }}"
                                    alt="{{ $avatar['alt'] ?? $review['author'] }}"
                                    width="{{ (int) $avatar['width'] }}"
                                    height="{{ (int) $avatar['height'] }}"
                                    loading="lazy"
                                >
                            @else
                                <span class="review-carousel-initials" aria-hidden="true">{{ $initials }}</span>
                            @endif

                            <span>
                                <strong>{{ $review['author'] }}</strong>
                                @if (filled($review['context']))
                                    <small>{{ $review['context'] }}</small>
                                @endif
                            </span>
                        </figcaption>
                    </figure>
                @endif
            @endforeach
        </div>
    </div>

    @if (count($items) > 1)
        <nav class="review-carousel-dots" aria-label="Choose a customer review">
            @foreach ($items as $index => $review)
                <button
                    type="button"
                    class="review-carousel-dot{{ $index === $active ? ' is-active' : '' }}"
                    wire:click="select({{ $index }})"
                    aria-current="{{ $index === $active ? 'true' : 'false' }}"
                    aria-label="{{ ($review['type'] ?? 'quote') === 'video'
                        ? 'Show video '.($review['title'] ?? '')
                        : 'Show review from '.$review['author'] }}"
                >
                    <span aria-hidden="true"></span>
                </button>
            @endforeach
        </nav>
    @endif
</section>
