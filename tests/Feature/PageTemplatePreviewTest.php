<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PageTemplatePreviewTest extends TestCase
{
    #[DataProvider('previewProvider')]
    public function test_fixed_page_previews_are_complete_fictional_noindex_pages(string $slug, string $title): void
    {
        $response = $this->get('/_templates/'.$slug)
            ->assertOk()
            ->assertSee($title)
            ->assertSee('Fictional', false)
            ->assertSee('name="robots" content="noindex, nofollow"', false);

        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public static function previewProvider(): iterable
    {
        yield ['service-pillar', 'Service pillar'];
        yield ['location-pillar', 'Location pillar'];
        yield ['decision-guide', 'Decision guide'];
    }
}
