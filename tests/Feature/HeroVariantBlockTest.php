<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HeroVariantBlockTest extends TestCase
{
    #[DataProvider('heroProvider')]
    public function test_each_hero_owns_exactly_one_h1_and_filters_unsafe_actions(
        string $component,
        array $data,
        array $expected,
    ): void {
        $html = Blade::render($component, $data);

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertSame(0, substr_count($html, '<h2'));
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('Unsafe action', $html);
        foreach ($expected as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
    }

    public static function heroProvider(): iterable
    {
        $actions = [
            'primaryAction' => ['label' => 'Request a visit', 'href' => '#quote'],
            'secondaryAction' => ['label' => 'Unsafe action', 'href' => 'javascript:alert(1)'],
        ];

        yield 'interior' => [
            '<x-blocks.hero.interior heading="About this fictional team" body="A calm interior page introduction." :primary-action="$primaryAction" :secondary-action="$secondaryAction" />',
            $actions,
            ['About this fictional team', 'Request a visit'],
        ];

        yield 'service' => [
            '<x-blocks.hero.service heading="Fictional air duct service" body="A clear service page introduction." :primary-action="$primaryAction" :secondary-action="$secondaryAction" />',
            $actions,
            ['Fictional air duct service', 'Request a visit'],
        ];

        yield 'location' => [
            '<x-blocks.hero.location heading="Fictional air duct service in North Valley" place="North Valley" body="Mobile service for the surrounding community." :primary-action="$primaryAction" :secondary-action="$secondaryAction" />',
            $actions,
            ['Fictional air duct service in North Valley', 'Serving North Valley and nearby communities', 'Request a visit'],
        ];

        yield 'article' => [
            '<x-blocks.hero.article heading="A fictional guide to cleaner ducts" summary="A practical educational introduction." author="Jordan Example" published-at="2026-07-20" :reviewer="$reviewer" />',
            ['reviewer' => ['name' => 'Taylor Example', 'reviewed_at' => '2026-07-22']],
            ['A fictional guide to cleaner ducts', 'Jordan Example', 'datetime="2026-07-20"', 'Taylor Example', 'datetime="2026-07-22"'],
        ];
    }
}
