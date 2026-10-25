<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class TestimonialCarouselBlockTest extends TestCase
{
    public function test_it_renders_explicit_reviews_with_optional_card_fields(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('data-testimonial-variant="carousel"', $html);
        $this->assertStringContainsString('Care you can feel', $html);
        $this->assertStringContainsString('Example review text.', $html);
        $this->assertStringContainsString('Example Customer', $html);
        $this->assertStringContainsString('Template example only', $html);
        $this->assertStringContainsString('src="/media/example-avatar"', $html);
        $this->assertStringContainsString('alt="Example Customer"', $html);
        $this->assertStringContainsString('width="96"', $html);
        $this->assertStringContainsString('height="96"', $html);
    }

    public function test_it_renders_nothing_when_there_are_no_approved_testimonial_records(): void
    {
        $html = Blade::render(
            '<x-blocks.testimonials.carousel heading="Customer notes" />',
        );

        $this->assertSame('', trim($html));
    }

    public function test_an_explicit_empty_review_list_renders_no_section(): void
    {
        $html = Blade::render(
            '<x-blocks.testimonials.carousel heading="No reviews" :items="[]" />',
        );

        $this->assertSame('', trim($html));
    }

    public function test_review_copy_and_avatar_attributes_are_escaped(): void
    {
        $items = [[
            'title' => '<Headline>',
            'quote' => '<Quote>',
            'author' => '<Author>',
            'context' => '<Context>',
            'avatar' => [
                'src' => '"><script>',
                'alt' => '<Avatar>',
                'width' => 96,
                'height' => 96,
            ],
        ]];

        $html = Blade::render(
            '<x-blocks.testimonials.carousel heading="<Reviews>" :items="$items" />',
            ['items' => $items],
        );

        $this->assertStringContainsString('&lt;Reviews&gt;', $html);
        $this->assertStringContainsString('&lt;Headline&gt;', $html);
        $this->assertStringContainsString('&lt;Quote&gt;', $html);
        $this->assertStringContainsString('&lt;Author&gt;', $html);
        $this->assertStringContainsString('&lt;Context&gt;', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('alt="&lt;Avatar&gt;"', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_the_existing_quotes_grid_still_renders(): void
    {
        $html = Blade::render(
            '<x-blocks.testimonials.quotes heading="Existing testimonials" :items="$items" />',
            ['items' => [[
                'quote' => 'Existing quote.',
                'author' => 'Existing customer',
                'location' => 'Example location',
                'rating' => 5,
            ]]],
        );

        $this->assertStringContainsString('Existing testimonials', $html);
        $this->assertStringContainsString('Existing quote.', $html);
        $this->assertStringContainsString('Existing customer', $html);
    }

    private function render(): string
    {
        return Blade::render(
            '<x-blocks.testimonials.carousel heading="Example reviews" :items="$items" />',
            ['items' => [[
                'title' => 'Care you can feel',
                'quote' => 'Example review text.',
                'author' => 'Example Customer',
                'context' => 'Template example only',
                'avatar' => [
                    'src' => '/media/example-avatar',
                    'alt' => 'Example Customer',
                    'width' => 96,
                    'height' => 96,
                ],
            ]]],
        );
    }
}
