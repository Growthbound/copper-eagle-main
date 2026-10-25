<?php

namespace Tests\Feature;

use App\Livewire\QuoteForm;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

class SplitQuoteFormTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('s', 32)));
    }

    public function test_split_presentation_uses_the_compact_field_order_and_shared_form_contract(): void
    {
        $html = Livewire::test(QuoteForm::class, [
            'variant' => 'split',
            'showMessage' => false,
            'submitLabel' => 'Get a quote',
        ])->html();

        $this->assertStringContainsString('data-quote-form-variant="split"', $html);
        $this->assertStringContainsString('id="quote-form-first-name"', $html);
        $this->assertStringContainsString('id="quote-form-last-name"', $html);
        $this->assertStringContainsString('id="quote-form-email"', $html);
        $this->assertStringContainsString('id="quote-form-phone"', $html);
        $this->assertStringContainsString('id="quote-form-service"', $html);
        $this->assertStringContainsString('id="quote-form-equipment-model"', $html);
        $this->assertStringContainsString('id="quote-form-filter-size"', $html);
        $this->assertStringContainsString('Get a quote', $html);
        $this->assertStringNotContainsString('id="quote-form-message"', $html);
        $this->assertLessThan(
            strpos($html, 'id="quote-form-phone"'),
            strpos($html, 'id="quote-form-email"'),
            'Email must appear before phone in the split presentation.',
        );
    }

    public function test_split_presentation_can_restore_the_optional_message_field(): void
    {
        Livewire::test(QuoteForm::class, [
            'variant' => 'split',
            'showMessage' => true,
        ])->assertSeeHtml('id="quote-form-message"');
    }

    public function test_invalid_presentation_values_fall_back_without_changing_submission_behavior(): void
    {
        $component = Livewire::test(QuoteForm::class, [
            'variant' => 'unknown',
            'showMessage' => true,
            'submitLabel' => '  ',
        ]);

        $component
            ->assertSet('variant', 'card')
            ->assertSet('submitLabel', 'Request a visit')
            ->assertSeeHtml('data-quote-form-variant="card"')
            ->assertSeeHtml('id="quote-form-message"');
    }

    public function test_custom_submit_copy_is_escaped(): void
    {
        Livewire::test(QuoteForm::class, [
            'submitLabel' => '<strong>Unsafe</strong>',
        ])
            ->assertSee('&lt;strong&gt;Unsafe&lt;/strong&gt;', false)
            ->assertDontSee('<strong>Unsafe</strong>', false);
    }

    public function test_split_block_renders_intrinsic_service_media_and_the_shared_livewire_form(): void
    {
        $html = Blade::render(
            '<x-blocks.cta.quote-form-split heading="Request a quote" :image="$image" />',
            ['image' => [
                'src' => 'images/home/technician-maintenance.jpg',
                'alt' => 'Technician <inspecting> equipment',
                'width' => 1800,
                'height' => 3200,
            ]],
        );

        $this->assertStringContainsString('data-contact-form-variant="split"', $html);
        $this->assertStringContainsString('data-quote-form-variant="split"', $html);
        $this->assertStringContainsString('/images/home/technician-maintenance.jpg', $html);
        $this->assertStringContainsString('alt="Technician &lt;inspecting&gt; equipment"', $html);
        $this->assertStringContainsString('width="1800"', $html);
        $this->assertStringContainsString('height="3200"', $html);
        $this->assertStringNotContainsString('id="quote-form-message"', $html);
    }

    public function test_split_block_collapses_cleanly_when_media_metadata_is_incomplete(): void
    {
        $html = Blade::render(
            '<x-blocks.cta.quote-form-split heading="Request a quote" :image="$image" />',
            ['image' => [
                'src' => 'images/home/technician-maintenance.jpg',
                'alt' => 'Technician inspecting equipment',
            ]],
        );

        $this->assertStringContainsString('is-without-media', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('data-quote-form-variant="split"', $html);
    }

    public function test_template_preview_form_never_creates_a_real_lead(): void
    {
        Livewire::test(QuoteForm::class, ['preview' => true])
            ->set('firstName', 'Preview')
            ->set('lastName', 'Visitor')
            ->set('email', 'preview@example.com')
            ->set('phone', '555 555 0100')
            ->set('consent', true)
            ->call('submit')
            ->assertSet('sent', true);

        $this->assertDatabaseCount((new Lead)->getTable(), 0);
    }
}
