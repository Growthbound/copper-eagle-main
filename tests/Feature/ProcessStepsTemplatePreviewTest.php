<?php

namespace Tests\Feature;

use App\Support\MarketingIcon;
use Tests\TestCase;

class ProcessStepsTemplatePreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('i', 32)));
    }

    public function test_local_preview_renders_the_icon_process_and_approved_catalog_as_noindex(): void
    {
        $response = $this->get('/_templates/process-steps')
            ->assertOk()
            ->assertSee('Icon process template')
            ->assertSee('Template example — not public process content')
            ->assertSee('Approved marketing icon catalog')
            ->assertSee('data-process-variant="icon-grid"', false)
            ->assertSee('name="robots" content="noindex, nofollow"', false);

        foreach (array_keys(MarketingIcon::catalog()) as $icon) {
            $response->assertSee($icon);
        }
    }

    public function test_preview_is_absent_from_discovery_files(): void
    {
        foreach (['/sitemap.xml', '/robots.txt', '/llms.txt'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertDontSee('/_templates/process-steps');
        }
    }

    public function test_preview_returns_not_found_in_production(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        $this->get('/_templates/process-steps')->assertNotFound();
    }
}
