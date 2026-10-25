<?php

namespace Tests\Feature;

use Tests\TestCase;

class PricingTemplatePreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('p', 32)));
    }

    public function test_local_preview_renders_all_three_variants_as_noindex(): void
    {
        $this->get('/_templates/pricing')
            ->assertOk()
            ->assertSee('Pricing sheet template gallery')
            ->assertSee('Monthly-only template')
            ->assertSee('Yearly-only template')
            ->assertSee('Monthly and yearly toggle template')
            ->assertSee('name="robots" content="noindex, nofollow"', false)
            ->assertSee('Template example — not live pricing');
    }

    public function test_preview_is_absent_from_discovery_files(): void
    {
        foreach (['/sitemap.xml', '/robots.txt', '/llms.txt'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertDontSee('/_templates/pricing');
        }
    }

    public function test_preview_returns_not_found_in_production(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        $this->get('/_templates/pricing')->assertNotFound();
    }
}
