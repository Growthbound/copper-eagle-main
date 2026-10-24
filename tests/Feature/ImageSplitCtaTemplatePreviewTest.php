<?php

namespace Tests\Feature;

use App\Support\TemplatePreviewCatalog;
use Tests\TestCase;

class ImageSplitCtaTemplatePreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('i', 32)));
    }

    public function test_local_preview_renders_the_image_split_cta_as_noindex(): void
    {
        $this->get('/_templates/image-split-cta')
            ->assertOk()
            ->assertSee('Questions about cleaner air?')
            ->assertSee('Explore homeowner resources')
            ->assertSee('data-cta-variant="image-split"', false)
            ->assertSee('src="http://localhost/images/home/technician-maintenance.jpg"', false)
            ->assertSee('width="1800"', false)
            ->assertSee('height="3200"', false)
            ->assertSee('name="robots" content="noindex, nofollow"', false);
    }

    public function test_preview_is_discovered_by_the_local_template_catalog(): void
    {
        $templates = collect(app(TemplatePreviewCatalog::class)->all());

        $this->assertTrue($templates->contains(
            fn (array $template): bool => $template['slug'] === 'image-split-cta'
                && str_ends_with($template['href'], '/_templates/image-split-cta'),
        ));

        $this->get('/templates')
            ->assertOk()
            ->assertSee('Image Split Cta')
            ->assertSee('/_templates/image-split-cta');
    }

    public function test_preview_is_absent_from_discovery_files(): void
    {
        foreach (['/sitemap.xml', '/robots.txt', '/llms.txt'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertDontSee('/_templates/image-split-cta');
        }
    }

    public function test_preview_returns_not_found_in_production(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        $this->get('/_templates/image-split-cta')->assertNotFound();
    }
}
