<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use Livewire\Livewire;
use Tests\TestCase;

class PricingSheetBlockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Livewire keeps a static stack of the components it is rendering and
        // wraps every Blade conditional in morph markers while that stack is
        // non-empty. A Livewire render that threw in an earlier test never
        // pops its entry, so these plain Blade renders would pick up markers
        // depending on test order. Start each test from a clean stack.
        Livewire::flushState();
    }

    public function test_monthly_variant_renders_only_monthly_prices_without_a_selector(): void
    {
        $html = $this->render('monthly');

        $this->assertStringContainsString('data-pricing-sheet="monthly"', $html);
        $this->assertStringContainsString('$120', $html);
        $this->assertStringNotContainsString('$1,200', $html);
        $this->assertStringNotContainsString('data-pricing-modes', $html);
    }

    public function test_yearly_variant_renders_only_yearly_prices_without_a_selector(): void
    {
        $html = $this->render('yearly');

        $this->assertStringContainsString('data-pricing-sheet="yearly"', $html);
        $this->assertStringContainsString('$1,200', $html);
        $this->assertStringNotContainsString('$120', $html);
        $this->assertStringNotContainsString('data-pricing-modes', $html);
    }

    public function test_single_mode_variants_skip_plans_without_the_required_price(): void
    {
        $plans = $this->plans();
        unset($plans[1]['prices']['monthly']);

        $html = Blade::render(
            '<x-blocks.pricing.monthly heading="Template pricing" :plans="$plans" />',
            ['plans' => $plans],
        );

        $this->assertStringContainsString('Starter example', $html);
        $this->assertStringNotContainsString('Custom example', $html);
    }

    public function test_empty_plans_render_no_section(): void
    {
        $html = Blade::render(
            '<x-blocks.pricing.monthly heading="Template pricing" :plans="[]" />',
        );

        $this->assertSame('', trim($html));
    }

    public function test_optional_card_content_omits_cleanly(): void
    {
        $plans = $this->plans();
        unset($plans[0]['media'], $plans[0]['featureHeading'], $plans[0]['features'], $plans[0]['cta']);

        $html = Blade::render(
            '<x-blocks.pricing.monthly heading="Template pricing" :plans="$plans" />',
            ['plans' => [$plans[0]]],
        );

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('pricing-feature-list', $html);
        $this->assertStringNotContainsString('pricing-card-cta', $html);
        $this->assertStringNotContainsString('href=""', $html);
    }

    public function test_one_through_four_cards_use_the_same_grid_contract(): void
    {
        for ($count = 1; $count <= 4; $count++) {
            $plans = array_slice(array_merge($this->plans(), $this->plans()), 0, $count);
            $html = Blade::render(
                '<x-blocks.pricing.monthly heading="Template pricing" :plans="$plans" />',
                ['plans' => $plans],
            );

            $this->assertSame($count, substr_count($html, 'data-pricing-plan'));
        }
    }

    public function test_toggle_variant_requires_both_price_modes(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('Pricing plan 1 needs a yearly amount for the toggle variant.');

        $plans = $this->plans();
        unset($plans[0]['prices']['yearly']);

        Blade::render(
            '<x-blocks.pricing.toggle heading="Template pricing" :plans="$plans" />',
            ['plans' => $plans],
        );
    }

    public function test_toggle_renders_an_accessible_monthly_yearly_selector(): void
    {
        $html = Blade::render(
            '<x-blocks.pricing.toggle heading="Template pricing" default-mode="yearly" :modes="$modes" :plans="$plans" />',
            [
                'modes' => [
                    'monthly' => ['label' => 'Monthly', 'badge' => null],
                    'yearly' => ['label' => 'Yearly', 'badge' => 'Example saving'],
                ],
                'plans' => $this->plans(),
            ],
        );

        $this->assertStringContainsString('data-pricing-sheet="toggle"', $html);
        $this->assertStringContainsString('data-pricing-modes', $html);
        $this->assertSame(2, substr_count($html, 'type="radio"'));
        $this->assertMatchesRegularExpression('/value="yearly"[^>]*checked/', $html);
        $this->assertStringContainsString('data-price-mode="monthly"', $html);
        $this->assertStringContainsString('data-price-mode="yearly"', $html);
        $this->assertStringContainsString('Example saving', $html);
    }

    public function test_toggle_rejects_an_unsupported_default_mode(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('defaultMode must be monthly or yearly');

        Blade::render(
            '<x-blocks.pricing.toggle heading="Template pricing" default-mode="weekly" :plans="$plans" />',
            ['plans' => $this->plans()],
        );
    }

    public function test_toggle_css_contains_both_mode_visibility_contracts(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString(
            '.pricing-sheet:has(.pricing-mode-input[value="monthly"]:checked) [data-price-mode="yearly"]',
            $css,
        );
        $this->assertStringContainsString(
            '.pricing-sheet:has(.pricing-mode-input[value="yearly"]:checked) [data-price-mode="monthly"]',
            $css,
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function plans(): array
    {
        return [
            [
                'name' => 'Starter example',
                'summary' => 'Synthetic preview content.',
                'prices' => [
                    'monthly' => ['amount' => '$120', 'suffix' => '/ month', 'note' => 'Example only'],
                    'yearly' => ['amount' => '$1,200', 'suffix' => '/ year', 'note' => 'Example only'],
                ],
                'featureHeading' => 'Example features',
                'features' => ['First example feature', 'Second example feature'],
                'cta' => ['label' => 'Example action', 'href' => '#template-end'],
            ],
            [
                'name' => 'Custom example',
                'summary' => 'Synthetic preview content.',
                'prices' => [
                    'monthly' => ['amount' => 'Request an estimate', 'suffix' => null, 'note' => null],
                    'yearly' => ['amount' => 'Request an estimate', 'suffix' => null, 'note' => null],
                ],
                'featureHeading' => null,
                'features' => [],
                'cta' => null,
                'featured' => true,
            ],
        ];
    }

    private function render(string $variant): string
    {
        return Blade::render(
            '<x-dynamic-component :component="$component" heading="Template pricing" :plans="$plans" />',
            [
                'component' => 'blocks.pricing.'.$variant,
                'plans' => $this->plans(),
            ],
        );
    }
}
