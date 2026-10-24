<?php

namespace Tests\Feature;

use App\Support\TemplatePreviewCatalog;
use Tests\TestCase;

class TemplateGalleryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('t', 32)));
    }

    public function test_gallery_discovers_every_preview_and_links_to_a_working_full_page(): void
    {
        $templates = app(TemplatePreviewCatalog::class)->all();

        $response = $this->get('/templates')
            ->assertOk()
            ->assertSee('Template library')
            ->assertSee('name="robots" content="noindex, nofollow"', false)
            ->assertSee('data-template-gallery', false)
            ->assertSee(count($templates).' templates')
            ->assertSee(app()->environment().' environment');

        $this->assertNotEmpty($templates);
        $this->assertSame(count($templates), substr_count($response->getContent(), 'class="template-gallery-card"'));

        foreach ($templates as $template) {
            $response
                ->assertSee($template['title'])
                ->assertSee($template['href']);

            $path = parse_url($template['href'], PHP_URL_PATH);

            $this->assertIsString($path);
            $this->get($path)->assertOk();
        }
    }

    public function test_staging_can_access_the_gallery_and_individual_previews(): void
    {
        app()->detectEnvironment(fn (): string => 'staging');

        $this->get('/templates')->assertOk();
        $this->get('/_templates/faq')->assertOk();
    }

    public function test_production_cannot_access_the_gallery_or_individual_previews(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        $this->get('/templates')->assertNotFound();
        $this->get('/_templates/faq')->assertNotFound();
    }

    public function test_gallery_is_absent_from_public_navigation_and_discovery_files(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('href="/templates"', false);

        foreach (['/sitemap.xml', '/llms.txt'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertDontSee('/templates');
        }

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /templates', false);
    }
}
