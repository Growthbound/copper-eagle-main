<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\Concerns\WritesRecordFixtures;
use Tests\TestCase;

class ManagedBlockRegressionTest extends TestCase
{
    use WritesRecordFixtures;

    protected function tearDown(): void
    {
        $this->tearDownRecordFixtures();

        parent::tearDown();
    }

    public function test_resource_cards_render_the_optional_managed_destination(): void
    {
        $html = Blade::render(
            '<x-blocks.content.resource-cards heading="Resources" :items="$items" />',
            ['items' => [[
                'title' => 'Getting started guide',
                'body' => 'A practical guide.',
                'href' => '/guides/getting-started',
            ]]],
        );

        $this->assertStringContainsString('href="/guides/getting-started"', $html);
        $this->assertStringContainsString('Read Getting started guide', $html);
    }

    public function test_multiple_quote_forms_and_benefit_carousels_emit_unique_dom_ids(): void
    {
        // A carousel with no benefit records renders an empty panel, so give
        // both carousels something to show.
        $this->writeRecordFixture('benefits', 'fixture-benefit', [
            'name' => 'Arrives when promised',
            'summary' => 'Visits start inside the window we agreed.',
        ]);

        $html = Blade::render(<<<'BLADE'
            <x-blocks.cta.quote-form id="first-quote" heading="First request" />
            <x-blocks.cta.quote-form-split id="second-quote" heading="Second request" />
            <x-blocks.content.benefit-carousel id="first-benefits" />
            <x-blocks.content.benefit-carousel id="second-benefits" />
        BLADE);

        preg_match_all('/\sid="([^"]+)"/', $html, $matches);

        $this->assertSame($matches[1], array_values(array_unique($matches[1])));
        $this->assertStringContainsString('id="first-quote-form-first-name"', $html);
        $this->assertStringContainsString('id="second-quote-form-first-name"', $html);
        $this->assertStringContainsString('id="first-benefits-title"', $html);
        $this->assertStringContainsString('id="second-benefits-title"', $html);
    }
}
