<?php

namespace Tests\Feature;

use App\Support\TemplatePreviewCatalog;
use Tests\TestCase;

class TeamGridTemplatePreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('m', 32)));
    }

    public function test_local_preview_renders_four_synthetic_team_cards_as_noindex(): void
    {
        $response = $this->get('/_templates/team-grid')
            ->assertOk()
            ->assertSee('Meet Our Indoor Air Team')
            ->assertSee('Alex Sample')
            ->assertSee('data-team-variant="grid"', false)
            ->assertSee('name="robots" content="noindex, nofollow"', false);

        $this->assertSame(4, substr_count($response->getContent(), 'class="team-card"'));
        $this->assertSame(4, substr_count($response->getContent(), 'class="team-card-portrait"'));
        $this->assertSame(4, substr_count($response->getContent(), 'loading="lazy"'));
    }

    public function test_preview_is_discovered_by_the_local_template_catalog(): void
    {
        $templates = collect(app(TemplatePreviewCatalog::class)->all());

        $this->assertTrue($templates->contains(
            fn (array $template): bool => $template['slug'] === 'team-grid'
                && str_ends_with($template['href'], '/_templates/team-grid'),
        ));

        $this->get('/templates')
            ->assertOk()
            ->assertSee('Team Grid')
            ->assertSee('/_templates/team-grid');
    }

    public function test_preview_is_absent_from_discovery_files(): void
    {
        foreach (['/sitemap.xml', '/robots.txt', '/llms.txt'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertDontSee('/_templates/team-grid');
        }
    }

    public function test_preview_returns_not_found_in_production(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        $this->get('/_templates/team-grid')->assertNotFound();
    }
}
