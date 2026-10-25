<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ImageSplitCtaBlockTest extends TestCase
{
    public function test_it_renders_complete_copy_action_and_contained_media(): void
    {
        $html = Blade::render(
            <<<'BLADE'
                <x-blocks.cta.image-split
                    id="resource-help"
                    eyebrow="Helpful resources"
                    heading="Questions about cleaner air?"
                    body="Start with practical guidance for a healthier home."
                    :cta="$cta"
                    :image="$image"
                />
            BLADE,
            [
                'cta' => [
                    'label' => 'Explore resources',
                    'href' => '#resources',
                ],
                'image' => [
                    'src' => 'images/home/technician-maintenance.jpg',
                    'alt' => 'Technician servicing home equipment',
                    'width' => 1800,
                    'height' => 3200,
                    'fit' => 'contain',
                ],
            ],
        );

        $this->assertStringContainsString('id="resource-help"', $html);
        $this->assertStringContainsString('class="cta-image-split"', $html);
        $this->assertStringNotContainsString('edge-panel', $html);
        $this->assertStringContainsString('aria-labelledby="resource-help-title"', $html);
        $this->assertStringContainsString('data-cta-variant="image-split"', $html);
        $this->assertStringContainsString('Helpful resources', $html);
        $this->assertStringContainsString('Questions about cleaner air?', $html);
        $this->assertStringContainsString('Start with practical guidance for a healthier home.', $html);
        $this->assertStringContainsString('href="#resources"', $html);
        $this->assertStringContainsString('Explore resources', $html);
        $this->assertStringContainsString('class="cta-image-split-media is-contain"', $html);
        $this->assertStringContainsString('src="http://localhost/images/home/technician-maintenance.jpg"', $html);
        $this->assertStringContainsString('alt="Technician servicing home equipment"', $html);
        $this->assertStringContainsString('width="1800"', $html);
        $this->assertStringContainsString('height="3200"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
    }

    public function test_public_strings_and_attributes_are_escaped(): void
    {
        $html = Blade::render(
            <<<'BLADE'
                <x-blocks.cta.image-split
                    id="safe-cta"
                    :eyebrow="$eyebrow"
                    :heading="$heading"
                    :body="$body"
                    :cta="$cta"
                    :image="$image"
                />
            BLADE,
            [
                'eyebrow' => '<Context>',
                'heading' => '<script>alert("heading")</script>',
                'body' => '<strong>Body</strong>',
                'cta' => [
                    'label' => '<Explore>',
                    'href' => '"><script>alert("href")</script>',
                ],
                'image' => [
                    'src' => 'images/home/technician-controls.jpg',
                    'alt' => '<Technician>',
                    'width' => 1600,
                    'height' => 2844,
                ],
            ],
        );

        $this->assertStringContainsString('&lt;Context&gt;', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;heading&quot;)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;strong&gt;Body&lt;/strong&gt;', $html);
        $this->assertStringContainsString('&lt;Explore&gt;', $html);
        $this->assertStringContainsString('alt="&lt;Technician&gt;"', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<strong>', $html);
    }

    public function test_incomplete_optional_structures_are_omitted(): void
    {
        $html = Blade::render(
            '<x-blocks.cta.image-split heading="A focused next step" :cta="$cta" :image="$image" />',
            [
                'cta' => [
                    'label' => 'Missing destination',
                ],
                'image' => [
                    'src' => 'images/home/technician-maintenance.jpg',
                    'alt' => 'Technician servicing home equipment',
                    'width' => 0,
                    'height' => 3200,
                ],
            ],
        );

        $this->assertStringContainsString('A focused next step', $html);
        $this->assertStringContainsString('is-without-media', $html);
        $this->assertStringNotContainsString('cta-image-split-media', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<a ', $html);
        $this->assertStringNotContainsString('Missing destination', $html);
    }
}
