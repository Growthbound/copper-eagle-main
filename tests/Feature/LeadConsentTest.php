<?php

namespace Tests\Feature;

use App\Livewire\Admin\Leads;
use App\Livewire\Admin\SiteDetailsEditor;
use App\Livewire\QuoteForm;
use App\Models\Lead;
use App\Models\User;
use App\Support\LeadConsent;
use App\Webhooks\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\Concerns\IsolatesManagedContent;
use Tests\TestCase;

class LeadConsentTest extends TestCase
{
    use IsolatesManagedContent;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('quote-form:127.0.0.1');
    }

    public function test_the_wording_is_saved_with_the_lead_not_merely_referenced(): void
    {
        LeadConsent::save('checkbox', 'I agree Example Co. may call or text me.', required: false);

        $lead = $this->submitForm(consent: true);

        $this->assertTrue($lead->consent_given);
        $this->assertSame(
            'I agree Example Co. may call or text me. See our Privacy Policy (/privacy-policy/) and Terms and Conditions (/terms-and-conditions/).',
            $lead->consent_text,
        );

        // Editing the wording must not rewrite what an earlier visitor agreed to.
        LeadConsent::save('checkbox', 'Completely different wording.', required: false);

        $this->assertSame(
            'I agree Example Co. may call or text me. See our Privacy Policy (/privacy-policy/) and Terms and Conditions (/terms-and-conditions/).',
            $lead->refresh()->consent_text,
        );
    }

    public function test_agreement_records_when_and_from_where(): void
    {
        LeadConsent::save('checkbox', 'I agree to be contacted.', required: false);

        $lead = $this->submitForm(consent: true);

        $this->assertNotNull($lead->consent_at);
        $this->assertSame('127.0.0.1', $lead->consent_ip);
    }

    public function test_an_unticked_box_is_recorded_as_not_agreed_but_still_accepts_the_enquiry(): void
    {
        LeadConsent::save('checkbox', 'I agree to be contacted.', required: false);

        $lead = $this->submitForm(consent: false);

        $this->assertFalse($lead->consent_given);
        $this->assertNull($lead->consent_at);
        $this->assertNull($lead->consent_ip);
        $this->assertSame(
            'I agree to be contacted. See our Privacy Policy (/privacy-policy/) and Terms and Conditions (/terms-and-conditions/).',
            $lead->consent_text,
        );
    }

    public function test_the_box_starts_unticked(): void
    {
        LeadConsent::save('checkbox', 'I agree to be contacted.', required: false);

        Livewire::test(QuoteForm::class)->assertSet('consent', false);
    }

    public function test_the_tick_can_be_made_mandatory(): void
    {
        LeadConsent::save('checkbox', 'I agree to be contacted.', required: true);

        Livewire::test(QuoteForm::class)
            ->set('name', 'Taylor Example')
            ->set('phone', '555 555 0100')
            ->call('submit')
            ->assertHasErrors('consent');

        $this->assertSame(0, Lead::query()->count());
    }

    public function test_disclosure_mode_records_agreement_without_a_box(): void
    {
        LeadConsent::save('disclosure', 'By submitting you agree we may contact you.', required: false);

        $lead = $this->submitForm();

        $this->assertTrue($lead->consent_given);
        $this->assertSame('By submitting you agree we may contact you.', $lead->consent_text);
        $this->assertNotNull($lead->consent_at);
    }

    public function test_nothing_is_recorded_when_consent_wording_is_switched_off(): void
    {
        LeadConsent::save('off', '', required: false);

        $lead = $this->submitForm();

        $this->assertFalse($lead->consent_given);
        $this->assertNull($lead->consent_text);
    }

    public function test_the_wording_appears_on_the_public_form(): void
    {
        LeadConsent::save('checkbox', 'I agree Example Co. may text me.', required: false);

        $this->get('/')->assertSee('I agree Example Co. may text me.');
    }

    public function test_disclosure_wording_appears_without_a_checkbox(): void
    {
        LeadConsent::save('disclosure', 'Submitting means we may contact you.', required: false);

        Livewire::test(QuoteForm::class)
            ->assertSee('Submitting means we may contact you.')
            ->assertDontSee('wire:model="consent"', false);
    }

    public function test_requiring_the_tick_is_off_until_chosen(): void
    {
        LeadConsent::save('checkbox', 'I agree to be contacted.', required: false);

        $this->assertFalse(LeadConsent::isRequired());
    }

    public function test_a_required_tick_is_ignored_outside_checkbox_mode(): void
    {
        LeadConsent::save('disclosure', 'Wording.', required: true);

        $this->assertFalse(LeadConsent::isRequired());
    }

    public function test_the_owner_can_change_the_wording_from_the_admin(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(SiteDetailsEditor::class)
            ->set('consentMode', 'checkbox')
            ->set('consentText', 'I agree to receive a call about my request.')
            ->set('consentRequired', true)
            ->call('saveConsent')
            ->assertHasNoErrors();

        $this->assertSame('I agree to receive a call about my request.', LeadConsent::text());
        $this->assertTrue(LeadConsent::isRequired());
    }

    public function test_wording_cannot_be_left_blank_while_it_is_being_shown(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(SiteDetailsEditor::class)
            ->set('consentMode', 'checkbox')
            ->set('consentText', '')
            ->call('saveConsent')
            ->assertHasErrors('consentText');
    }

    public function test_the_record_travels_with_the_webhook_payload(): void
    {
        LeadConsent::save('checkbox', 'I agree to be contacted.', required: false);

        $payload = WebhookDispatcher::leadPayload($this->submitForm(consent: true));

        $this->assertTrue($payload['consent']['given']);
        $this->assertSame(
            'I agree to be contacted. See our Privacy Policy (/privacy-policy/) and Terms and Conditions (/terms-and-conditions/).',
            $payload['consent']['text'],
        );
        $this->assertNotNull($payload['consent']['at']);
    }

    public function test_the_export_carries_the_evidence(): void
    {
        $this->actingAs(User::factory()->create());

        LeadConsent::save('checkbox', 'I agree to be contacted by text.', required: false);
        $this->submitForm(consent: true);

        $download = Livewire::test(Leads::class)->instance()->export();

        ob_start();
        $download->sendContent();
        $csv = (string) ob_get_clean();

        $this->assertStringContainsString('Consent wording', $csv);
        $this->assertStringContainsString('I agree to be contacted by text.', $csv);
    }

    public function test_the_inbox_shows_what_was_agreed(): void
    {
        $this->actingAs(User::factory()->create());

        LeadConsent::save('checkbox', 'I agree to be contacted by text.', required: false);
        $lead = $this->submitForm(consent: true);

        Livewire::test(Leads::class)
            ->call('toggle', $lead->id)
            ->assertSee('Contact permission')
            ->assertSee('I agree to be contacted by text.');
    }

    private function submitForm(bool $consent = false): Lead
    {
        Livewire::test(QuoteForm::class)
            ->set('name', 'Taylor Example')
            ->set('phone', '555 555 0100')
            ->set('consent', $consent)
            ->call('submit')
            ->assertHasNoErrors();

        return Lead::query()->latest('id')->firstOrFail();
    }
}
