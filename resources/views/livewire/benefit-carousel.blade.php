{{--
    With no benefit records there is nothing to rotate. The section stays as
    the component's single root element, but hidden and empty.
--}}
<section id="{{ $carouselId }}" class="benefit-panel edge-panel dotted-panel" aria-labelledby="{{ $carouselId }}-title" aria-roledescription="carousel"{{ $benefits === [] ? ' hidden' : '' }}>
    @if ($benefits !== [])
        <div class="section-heading text-on-primary">
            <p class="eyebrow text-accent">{{ $eyebrow }}</p>
            <h2 id="{{ $carouselId }}-title">{{ $heading }}</h2>
        </div>

        <div class="benefit-stage" aria-live="polite">
            <span class="benefit-ghost benefit-ghost-left" aria-hidden="true"></span>
            <article class="benefit-card" wire:key="benefit-{{ $active }}">
                <span class="quote-mark" aria-hidden="true">“</span>
                <h3>{{ $benefits[$active]['title'] }}</h3>
                <p>{{ $benefits[$active]['body'] }}</p>
            </article>
            <span class="benefit-ghost benefit-ghost-right" aria-hidden="true"></span>
        </div>

        <div class="carousel-controls">
            <button type="button" wire:click="previous" aria-label="Show previous benefit">←</button>
            <span>{{ $active + 1 }} / {{ count($benefits) }}</span>
            <button type="button" wire:click="next" aria-label="Show next benefit">→</button>
        </div>
    @endif
</section>
