<?php

namespace Tests\Feature;

use Tests\TestCase;

class DynamicTemplatePreviewRouteTest extends TestCase
{
    public function test_allow_listed_preview_resolves_through_dynamic_route(): void
    {
        $this->get('/_templates/rich-content')
            ->assertOk()
            ->assertSee('Rich content system');
    }

    public function test_missing_malformed_and_traversal_preview_names_are_rejected(): void
    {
        $this->get('/_templates/not-a-real-preview')->assertNotFound();
        $this->get('/_templates/Bad_Name')->assertNotFound();
        $this->get('/_templates/%2e%2e%2fadmin')->assertNotFound();
    }

    public function test_dynamic_previews_are_unavailable_in_production(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        $this->get('/_templates/rich-content')->assertNotFound();
    }
}
