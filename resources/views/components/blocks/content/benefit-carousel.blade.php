{{--
    The rotating benefit panel. Named for what it shows: these are benefit
    records, not customer testimonials — those live in testimonials/quotes.
--}}
@props([
    'id' => 'benefits',
    'eyebrow' => null,
    'heading' => null,
])

<livewire:benefit-carousel
    :carousel-id="$id"
    :eyebrow="filled($eyebrow) ? $eyebrow : 'The service standard'"
    :heading="filled($heading) ? $heading : 'What Professional Care Should Feel Like'"
    :key="'benefit-carousel-'.$id"
/>
