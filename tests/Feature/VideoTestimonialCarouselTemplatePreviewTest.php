<?php

namespace Tests\Feature;

use Tests\TestCase;

class VideoTestimonialCarouselTemplatePreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('v', 32)));
    }

    public function test_local_preview_renders_the_mixed_media_carousel_as_noindex(): void
    {
        $response = $this->get('/_templates/video-testimonial-carousel')
            ->assertOk()
            ->assertSee('See What Our Customers Are Saying')
            ->assertSee('Mixed-media carousel template — synthetic content only')
            ->assertSee('data-testimonial-variant="carousel"', false)
            ->assertSee('data-card-type="video"', false)
            ->assertSee('data-card-type="quote"', false)
            ->assertSee('name="robots" content="noindex, nofollow"', false);

        $this->assertSame(2, substr_count($response->getContent(), 'data-card-type="video"'));
        $this->assertSame(4, substr_count($response->getContent(), 'wire:click="select('));
    }

    public function test_preview_is_absent_from_discovery_files(): void
    {
        foreach (['/sitemap.xml', '/robots.txt', '/llms.txt'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertDontSee('/_templates/video-testimonial-carousel');
        }
    }

    public function test_preview_returns_not_found_in_production(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        $this->get('/_templates/video-testimonial-carousel')->assertNotFound();
    }
}
