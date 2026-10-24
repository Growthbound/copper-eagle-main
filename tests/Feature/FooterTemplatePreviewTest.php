<?php

namespace Tests\Feature;

use Tests\TestCase;

class FooterTemplatePreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('p', 32)));
    }

    public function test_local_preview_renders_the_structured_footer_as_noindex(): void
    {
        $this->get('/_templates/footer')
            ->assertOk()
            ->assertSee('Structured footer template')
            ->assertSee('Template example — contact details are local preview data')
            ->assertSee('data-footer-variant="structured"', false)
            ->assertSee('name="robots" content="noindex, nofollow"', false)
            ->assertSee('hello@example.test')
            ->assertSee('Instagram')
            ->assertDontSee('class="site-footer"', false);
    }

    public function test_preview_is_absent_from_discovery_files(): void
    {
        foreach (['/sitemap.xml', '/robots.txt', '/llms.txt'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertDontSee('/_templates/footer');
        }
    }

    public function test_preview_returns_not_found_in_production(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        $this->get('/_templates/footer')->assertNotFound();
    }
}
