{{--
    Carousel variant for permissioned customer testimonials. Public review-feed
    aggregates remain separate and must not be filtered into this component.
--}}
@props([
    'id' => 'testimonial-carousel',
    'eyebrow' => null,
    'heading' => 'What customers say',
    'service' => null,
    'area' => null,
    'items' => null,
])

@php
    $source = $items ?? app(App\Content\TestimonialRepository::class)
        ->for($service, $area)
        ->map(fn (App\Content\Testimonial $testimonial): array => [
            'title' => null,
            'quote' => $testimonial->quote(),
            'author' => $testimonial->author(),
            'context' => $testimonial->location(),
            'avatar' => is_array($testimonial->get('avatar'))
                ? $testimonial->get('avatar')
                : null,
        ])
        ->all();

    $reviews = collect($source)
        ->filter(fn (mixed $item): bool => is_array($item))
        ->map(fn (array $item): array => [
            'type' => ($item['type'] ?? null) === 'video' ? 'video' : 'quote',
            'title' => filled($item['title'] ?? null) ? (string) $item['title'] : null,
            'quote' => (string) ($item['quote'] ?? ''),
            'author' => (string) ($item['author'] ?? ''),
            'context' => filled($item['context'] ?? null) ? (string) $item['context'] : null,
            'avatar' => is_array($item['avatar'] ?? null) ? $item['avatar'] : null,
            'poster' => is_array($item['poster'] ?? null) ? $item['poster'] : null,
            'video_url' => filled($item['video_url'] ?? null) ? (string) $item['video_url'] : null,
            'transcript' => filled($item['transcript'] ?? null) ? (string) $item['transcript'] : null,
        ])
        ->values()
        ->all();
@endphp

@if (filled($reviews))
    <livewire:testimonial-carousel
        :items="$reviews"
        :carousel-id="$id"
        :eyebrow="$eyebrow"
        :heading="$heading"
        :key="'testimonial-carousel-'.$id"
    />
@endif
