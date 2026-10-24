<?php

namespace Tests\Unit;

use App\Support\MarketingIcon;
use Illuminate\Support\Facades\Blade;
use InvalidArgumentException;
use Tests\TestCase;

class MarketingIconTest extends TestCase
{
    public function test_the_catalog_exposes_approved_icons_with_usage_guidance(): void
    {
        $icons = MarketingIcon::catalog();

        $this->assertArrayHasKey('calendar-days', $icons);
        $this->assertArrayHasKey('magnifying-glass', $icons);
        $this->assertArrayHasKey('sparkles', $icons);
        $this->assertArrayHasKey('wrench-screwdriver', $icons);

        foreach ($icons as $name => $guidance) {
            $this->assertIsString($name);
            $this->assertNotSame('', trim($name));
            $this->assertIsString($guidance);
            $this->assertNotSame('', trim($guidance));
        }
    }

    public function test_every_approved_icon_renders_through_the_decorative_wrapper(): void
    {
        foreach (array_keys(MarketingIcon::catalog()) as $name) {
            $html = Blade::render(
                '<x-marketing-icon :name="$name" class="example-icon" />',
                ['name' => $name],
            );

            $this->assertStringContainsString('<svg', $html, "The {$name} icon did not render an SVG.");
            $this->assertStringContainsString('marketing-icon', $html);
            $this->assertStringContainsString('example-icon', $html);
            $this->assertStringContainsString('aria-hidden="true"', $html);
        }
    }

    public function test_component_names_are_resolved_to_outline_heroicons(): void
    {
        $this->assertSame(
            'heroicon-o-calendar-days',
            MarketingIcon::component('calendar-days'),
        );
    }

    public function test_unknown_icon_names_fail_with_the_approved_choices(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown marketing icon [made-up-icon]. Approved icons:');

        MarketingIcon::component('made-up-icon');
    }
}
