<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocalConversionTemplatePreviewTest extends TestCase
{
    #[DataProvider('previewProvider')]
    public function test_every_local_conversion_preview_is_noindex_and_gallery_discovered(string $slug, string $title): void
    {
        $this->get('/_templates/'.$slug)
            ->assertOk()
            ->assertSee($title)
            ->assertSee('Fictional', false)
            ->assertSee('name="robots" content="noindex, nofollow"', false);

        $this->get('/templates')
            ->assertOk()
            ->assertSee('/_templates/'.$slug);
    }

    #[DataProvider('previewProvider')]
    public function test_every_local_conversion_preview_is_unavailable_in_production(string $slug, string $title): void
    {
        $this->assertNotSame('', $title);
        app()->detectEnvironment(fn (): string => 'production');

        $this->get('/_templates/'.$slug)->assertNotFound();
    }

    public static function previewProvider(): iterable
    {
        yield ['service-area-grid', 'Service area grid'];
        yield ['coverage-map', 'Coverage map'];
        yield ['local-project', 'Local project'];
        yield ['sticky-mobile-cta', 'Sticky mobile CTA'];
        yield ['inline-cta', 'Inline CTA'];
        yield ['form-confirmation', 'Form confirmation'];
        yield ['visit-expectations', 'Visit expectations'];
        yield ['interior-hero', 'Interior hero'];
        yield ['service-hero', 'Service hero'];
        yield ['location-hero', 'Location hero'];
        yield ['article-hero', 'Article hero'];
    }
}
