<?php

namespace Tests\Feature;

use Tests\TestCase;

class StoryChecklistTemplatePreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('v', 32)));
    }

    public function test_local_preview_renders_the_story_checklist_as_noindex(): void
    {
        $this->get('/_templates/story-checklist')
            ->assertOk()
            ->assertSee('Story checklist template')
            ->assertSee('Template example — not public company copy')
            ->assertSee('data-content-variant="story-checklist"', false)
            ->assertSee('/images/home/technician-maintenance.jpg')
            ->assertSee('/images/home/technician-detail.jpg')
            ->assertSee('width="1800"', false)
            ->assertSee('height="3200"', false)
            ->assertSee('width="1600"', false)
            ->assertSee('height="2845"', false)
            ->assertSee('name="robots" content="noindex, nofollow"', false);
    }

    public function test_preview_is_absent_from_discovery_files(): void
    {
        foreach (['/sitemap.xml', '/robots.txt', '/llms.txt'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertDontSee('/_templates/story-checklist');
        }
    }

    public function test_preview_returns_not_found_in_production(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        $this->get('/_templates/story-checklist')->assertNotFound();
    }
}
