@php
    /*
     * Synthetic examples exist only to exercise every template field. Public
     * pages must use permissioned records from content/testimonials instead.
     */
    $reviews = [
        [
            'title' => 'A clear, comfortable experience',
            'quote' => 'This is synthetic preview copy showing how a concise customer story fits inside the review card.',
            'author' => 'Example Customer 1',
            'context' => 'Template example only',
        ],
        [
            'title' => 'Professional from start to finish',
            'quote' => 'This placeholder demonstrates a review with enough detail to wrap naturally across multiple lines.',
            'author' => 'Example Customer 2',
            'context' => 'Template example only',
        ],
        [
            'title' => 'The job was done right',
            'quote' => 'This sample quote gives an agent a reliable picture of the intended card rhythm and content density.',
            'author' => 'Example Customer 3',
            'context' => 'Template example only',
        ],
        [
            'title' => 'Careful work and clear answers',
            'quote' => 'This fictional review is present for interaction testing and is not a public customer endorsement.',
            'author' => 'Example Customer 4',
            'context' => 'Template example only',
        ],
        [
            'title' => 'Easy to recommend',
            'quote' => 'This synthetic story keeps the preview realistic without inventing a claim about the business.',
            'author' => 'Example Customer 5',
            'context' => 'Template example only',
        ],
        [
            'title' => 'A thoughtful service visit',
            'quote' => 'This final placeholder confirms the carousel can rotate through a larger review collection.',
            'author' => 'Example Customer 6',
            'context' => 'Template example only',
        ],
    ];
@endphp

<x-layouts.marketing title="Review carousel template" :noindex="true">
    <x-blocks.testimonials.carousel
        id="testimonial-carousel-preview"
        eyebrow="Template example — synthetic reviews, not public endorsements"
        heading="Review carousel template"
        :items="$reviews"
    />
</x-layouts.marketing>
