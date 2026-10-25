<?php

namespace Tests\Feature;

use Tests\TestCase;

class FaqTemplatePreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('f', 32)));
    }

    public function test_local_preview_renders_the_highlighted_variant_as_noindex(): void
    {
        $this->get('/_templates/faq')
            ->assertOk()
            ->assertSee('Highlighted FAQ template')
            ->assertSee('Template example — not public FAQ content')
            ->assertSee('data-faq-variant="highlighted"', false)
            ->assertSee('name="robots" content="noindex, nofollow"', false);
    }

    public function test_preview_is_absent_from_discovery_files(): void
    {
        foreach (['/sitemap.xml', '/robots.txt', '/llms.txt'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertDontSee('/_templates/faq');
        }
    }

    public function test_preview_returns_not_found_in_production(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        $this->get('/_templates/faq')->assertNotFound();
    }
}
