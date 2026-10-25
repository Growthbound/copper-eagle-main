<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class LocalServiceBlockFamilyTest extends TestCase
{
    public function test_service_area_grid_keeps_only_named_safe_links(): void
    {
        $html = Blade::render(
            '<x-blocks.service-areas.grid heading="Areas we serve" :areas="$areas" />',
            ['areas' => [
                ['name' => 'North Valley', 'href' => '/areas/north-valley', 'note' => 'Mobile service available.'],
                ['name' => 'Unsafe', 'href' => 'javascript:alert(1)'],
                ['name' => '4296 Example Avenue', 'href' => '/areas/address'],
            ]],
        );

        $this->assertStringContainsString('North Valley', $html);
        $this->assertStringContainsString('href="/areas/north-valley"', $html);
        $this->assertStringContainsString('Mobile service available.', $html);
        $this->assertStringNotContainsString('Unsafe', $html);
        $this->assertStringNotContainsString('4296 Example Avenue', $html);
    }

    public function test_coverage_is_a_crawlable_service_area_list_without_storefront_signals(): void
    {
        $html = Blade::render(
            '<x-blocks.service-areas.coverage heading="Service coverage" coverage-label="Fictional mobile coverage illustration" :areas="$areas" />',
            ['areas' => [
                ['name' => 'North Valley', 'href' => '/areas/north-valley'],
                ['name' => 'West Ridge', 'href' => '/areas/west-ridge'],
            ]],
        );

        $this->assertStringContainsString('role="img"', $html);
        $this->assertStringContainsString('Fictional mobile coverage illustration', $html);
        $this->assertStringContainsString('North Valley', $html);
        $this->assertStringContainsString('West Ridge', $html);
        foreach (['address', 'directions', 'storefront', 'coordinates'] as $word) {
            $this->assertStringNotContainsString($word, strtolower($html));
        }
    }

    public function test_local_project_requires_a_safe_service_area_and_source(): void
    {
        $html = Blade::render(
            '<x-blocks.evidence.local-project heading="Fictional local project" place="North Valley" work="The approved components were serviced." outcome="The completed scope was documented." :source="$source" />',
            ['source' => ['label' => 'Fictional project record', 'href' => 'https://example.com/project']],
        );

        $this->assertStringContainsString('Service-area project', $html);
        $this->assertStringContainsString('North Valley', $html);
        $this->assertStringContainsString('The approved components were serviced.', $html);
        $this->assertStringContainsString('The completed scope was documented.', $html);
        $this->assertStringContainsString('Fictional project record', $html);

        $invalid = Blade::render(
            '<x-blocks.evidence.local-project heading="Invalid" place="4296 Example Avenue" work="Work." outcome="Outcome." :source="$source" />',
            ['source' => ['label' => 'Record', 'href' => 'javascript:alert(1)']],
        );

        $this->assertStringNotContainsString('<section', $invalid);
    }
}
