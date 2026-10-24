<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class VideoTestimonialCarouselBlockTest extends TestCase
{
    public function test_it_renders_video_and_quote_cards_in_one_carousel(): void
    {
        $html = Blade::render(
            '<x-blocks.testimonials.carousel heading="Customer stories" :items="$items" />',
            ['items' => [
                [
                    'type' => 'quote',
                    'title' => 'Care you can feel',
                    'quote' => 'Synthetic template quote.',
                    'author' => 'Example Customer',
                    'context' => 'Template example only',
                ],
                [
                    'type' => 'video',
                    'title' => 'Example customer story',
                    'context' => 'Template video only',
                    'video_url' => '/media/customer-story.mp4',
                    'transcript' => 'The customer explains the service in this synthetic transcript.',
                    'poster' => [
                        'src' => '/images/home/family-living-room.jpg',
                        'alt' => 'Example customer speaking on camera',
                        'width' => 1600,
                        'height' => 900,
                    ],
                ],
            ]],
        );

        $this->assertStringContainsString('data-card-type="quote"', $html);
        $this->assertStringContainsString('data-card-type="video"', $html);
        $this->assertStringContainsString('Synthetic template quote.', $html);
        $this->assertStringContainsString('src="/images/home/family-living-room.jpg"', $html);
        $this->assertStringContainsString('width="1600"', $html);
        $this->assertStringContainsString('height="900"', $html);
        $this->assertStringContainsString('href="/media/customer-story.mp4"', $html);
        $this->assertStringContainsString('aria-label="Play Example customer story"', $html);
        $this->assertStringContainsString('Read transcript', $html);
        $this->assertStringContainsString('The customer explains the service in this synthetic transcript.', $html);
    }

    public function test_video_fields_are_escaped_and_missing_urls_render_a_disabled_indicator(): void
    {
        $items = [
            [
                'type' => 'video',
                'title' => '<Video>',
                'context' => '<Context>',
                'video_url' => '"><script>',
                'transcript' => '<Transcript>',
                'poster' => [
                    'src' => '"><script>',
                    'alt' => '<Poster>',
                    'width' => 1200,
                    'height' => 675,
                ],
            ],
            [
                'type' => 'video',
                'title' => 'Poster-only example',
                'transcript' => 'Poster-only transcript.',
                'poster' => [
                    'src' => '/images/home/family-homework.jpg',
                    'alt' => 'Template poster',
                    'width' => 1600,
                    'height' => 900,
                ],
            ],
        ];

        $html = Blade::render(
            '<x-blocks.testimonials.carousel heading="<Stories>" :items="$items" />',
            ['items' => $items],
        );

        $this->assertStringContainsString('&lt;Stories&gt;', $html);
        $this->assertStringContainsString('&lt;Video&gt;', $html);
        $this->assertStringContainsString('&lt;Context&gt;', $html);
        $this->assertStringContainsString('alt="&lt;Poster&gt;"', $html);
        $this->assertStringContainsString('&lt;Transcript&gt;', $html);
        $this->assertStringContainsString('data-play-disabled="true"', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }
}
