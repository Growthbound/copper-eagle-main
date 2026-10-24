<?php

namespace Tests\Feature;

use Tests\TestCase;

class TestimonialTemplatePreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('r', 32)));
    }

    public function test_local_preview_renders_the_review_carousel_as_noindex(): void
    {
        $this->get('/_templates/testimonials')
            ->assertOk()
            ->assertSee('Review carousel template')
            ->assertSee('Template example — synthetic reviews, not public endorsements')
            ->assertSee('data-testimonial-variant="carousel"', false)
            ->assertSee('name="robots" content="noindex, nofollow"', false);
    }

    public function test_preview_is_absent_from_discovery_files(): void
    {
        foreach (['/sitemap.xml', '/robots.txt', '/llms.txt'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertDontSee('/_templates/testimonials');
        }
    }

    public function test_preview_returns_not_found_in_production(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        $this->get('/_templates/testimonials')->assertNotFound();
    }
}
