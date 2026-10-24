<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ConversionBlockFamilyTest extends TestCase
{
    public function test_sticky_mobile_cta_requires_one_safe_destination(): void
    {
        $html = Blade::render(
            '<x-blocks.cta.sticky-mobile :primary-action="$action" />',
            ['action' => ['label' => 'Request a visit', 'href' => '#quote']],
        );

        $this->assertStringContainsString('Request a visit', $html);
        $this->assertStringContainsString('href="#quote"', $html);

        $invalid = Blade::render(
            '<x-blocks.cta.sticky-mobile :primary-action="$action" />',
            ['action' => ['label' => 'Unsafe', 'href' => 'javascript:alert(1)']],
        );

        $this->assertStringNotContainsString('<aside', $invalid);
    }

    public function test_inline_cta_filters_unsafe_secondary_actions(): void
    {
        $html = Blade::render(
            '<x-blocks.cta.inline-band heading="Ready for a clearer next step?" body="Fictional template content." :primary-action="$primary" :secondary-action="$secondary" />',
            [
                'primary' => ['label' => 'Request a visit', 'href' => '#quote'],
                'secondary' => ['label' => 'Unsafe', 'href' => '//example.com'],
            ],
        );

        $this->assertStringContainsString('Request a visit', $html);
        $this->assertStringNotContainsString('Unsafe', $html);
    }

    public function test_confirmation_and_visit_expectation_blocks_are_structured_and_scannable(): void
    {
        $confirmation = Blade::render(
            '<x-blocks.cta.form-confirmation heading="What happens next" :steps="$steps" />',
            ['steps' => ['We review your request.', 'We confirm the scope.', 'You choose the next step.', '']],
        );
        $this->assertStringContainsString('<ol', $confirmation);
        $this->assertSame(3, substr_count($confirmation, '<li'));

        $expectations = Blade::render(
            '<x-blocks.cta.visit-expectations heading="What to expect" :items="$items" />',
            ['items' => [
                ['label' => 'Clear arrival window', 'description' => 'The fictional team confirms timing.', 'icon' => 'clock'],
                ['label' => 'Unsafe icon falls back', 'description' => 'Content remains readable.', 'icon' => 'not-real'],
            ]],
        );
        $this->assertStringContainsString('Clear arrival window', $expectations);
        $this->assertStringContainsString('Unsafe icon falls back', $expectations);
        $this->assertSame(2, substr_count($expectations, 'marketing-icon'));
    }
}
