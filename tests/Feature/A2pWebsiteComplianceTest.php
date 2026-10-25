<?php

namespace Tests\Feature;

use App\Livewire\QuoteForm;
use App\Models\Lead;
use App\Support\LeadConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\Concerns\IsolatesManagedContent;
use Tests\TestCase;

class A2pWebsiteComplianceTest extends TestCase
{
    use IsolatesManagedContent;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['website-forms.provider' => 'native']);
        RateLimiter::clear('quote-form:127.0.0.1');
    }

    public function test_native_inquiry_uses_a_separate_optional_unchecked_contact_consent(): void
    {
        $component = Livewire::test(QuoteForm::class)
            ->assertSet('consent', false)
            ->assertSee(config('website-forms.consent.text'))
            ->assertSee('text messages and calls from '.config('site.name'))
            ->assertSee('artificial or AI-generated voice')
            ->assertSee('Message frequency varies')
            ->assertSee('Message and data rates may apply')
            ->assertSee('Reply STOP to opt out or HELP for help')
            ->assertSee('Consent is not a condition of purchase');

        $html = $component->html();

        $this->assertStringContainsString('wire:model="consent"', $html);
        $this->assertDoesNotMatchRegularExpression('/wire:model="consent"[^>]*\brequired\b/s', $html);
        $this->assertStringContainsString('href="/privacy-policy/"', $html);
        $this->assertStringContainsString('href="/terms-and-conditions/"', $html);
        $this->assertSame('checkbox', LeadConsent::mode());
        $this->assertFalse(LeadConsent::isRequired());
    }

    public function test_an_inquiry_can_be_sent_without_contact_consent_and_records_the_decline(): void
    {
        Livewire::test(QuoteForm::class)
            ->set('name', 'Taylor Example')
            ->set('phone', '555 555 0100')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('sent', true);

        $lead = Lead::query()->sole();

        $this->assertFalse($lead->consent_given);
        $this->assertNull($lead->consent_at);
        $this->assertSame(LeadConsent::evidenceText(), $lead->consent_text);
    }

    public function test_the_default_footer_exposes_both_public_legal_pages(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('href="/privacy-policy/"', false)
            ->assertSee('href="/terms-and-conditions/"', false);
    }
}
