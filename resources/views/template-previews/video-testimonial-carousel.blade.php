@php
    /*
     * Every item below is synthetic and exists only to exercise the reusable
     * mixed-media carousel. Public pages must use permissioned testimonials
     * and client-approved video records.
     */
    $items = [
        [
            'type' => 'quote',
            'title' => 'Careful work, clearly explained',
            'quote' => 'This synthetic quote demonstrates the content density, attribution area, and center-card rhythm of the mixed-media carousel.',
            'author' => 'Example Customer 1',
            'context' => 'Template example only',
            'avatar' => [
                'src' => '/images/home/family-relaxing.jpg',
                'alt' => 'Synthetic template portrait',
                'width' => 1600,
                'height' => 2400,
            ],
        ],
        [
            'type' => 'video',
            'title' => 'Example customer video 2',
            'context' => 'Poster-only template example',
            'video_url' => '/media/example-customer-video-2.mp4',
            'transcript' => 'Synthetic transcript for the second example customer video. Replace this text and media with approved customer material before publication.',
            'poster' => [
                'src' => '/images/home/technician-controls.jpg',
                'alt' => 'Technician working with equipment controls',
                'width' => 1600,
                'height' => 2844,
            ],
        ],
        [
            'type' => 'quote',
            'title' => 'A comfortable service experience',
            'quote' => 'This fictional story lets a page-building agent see how another approved testimonial could rotate into the active position.',
            'author' => 'Example Customer 2',
            'context' => 'Template example only',
        ],
        [
            'type' => 'video',
            'title' => 'Example customer video 1',
            'context' => 'Poster-only template example',
            'video_url' => '/media/example-customer-video-1.mp4',
            'transcript' => 'Synthetic transcript for the first example customer video. Replace this text and media with approved customer material before publication.',
            'poster' => [
                'src' => '/images/home/technician-maintenance.jpg',
                'alt' => 'Technician servicing home equipment',
                'width' => 1800,
                'height' => 3200,
            ],
        ],
    ];
@endphp

<x-layouts.marketing title="Video testimonial carousel template" :noindex="true">
    <x-blocks.testimonials.carousel
        id="video-testimonial-carousel-preview"
        eyebrow="Mixed-media carousel template — synthetic content only"
        heading="See What Our Customers Are Saying"
        :items="$items"
    />
</x-layouts.marketing>
