<?php

namespace Tests\Feature;

use Tests\TestCase;

class RichContentTemplatePreviewTest extends TestCase
{
    public function test_rich_content_and_direct_answer_previews_are_noindex_specimens(): void
    {
        $this->get('/_templates/rich-content')
            ->assertOk()
            ->assertSee('Rich content system')
            ->assertSee('Page-owned H1')
            ->assertSee('Section-owned H2')
            ->assertSee('name="robots" content="noindex, nofollow"', false);

        $this->get('/_templates/direct-answer')
            ->assertOk()
            ->assertSee('Direct answer block')
            ->assertSee('Fictional template content')
            ->assertSee('name="robots" content="noindex, nofollow"', false);
    }

    public function test_new_previews_are_automatically_listed_but_never_discovered_publicly(): void
    {
        $this->get('/templates')
            ->assertOk()
            ->assertSee('/_templates/rich-content')
            ->assertSee('/_templates/direct-answer');

        foreach (['/sitemap.xml', '/robots.txt', '/llms.txt'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertDontSee('/_templates/rich-content')
                ->assertDontSee('/_templates/direct-answer');
        }
    }
}
