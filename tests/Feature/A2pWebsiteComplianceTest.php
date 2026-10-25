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

    /*
     * Copied word for word from the live contact form at
     * coppereagleplumbing.com/contact-copper-eagle-plumbing ("Contact Form v1").
     * Carriers compare the website opt-in with the registered campaign, so
     * these strings change only when the owner changes the registration.
     */
    private const LIVE_TITLE = 'Text Message Consent (optional)';

    private const LIVE_SERVICE = 'I agree to receive customer care and service text messages from Copper Eagle Plumbing LLC, including appointment confirmations, scheduling updates, technician arrival notices, estimates, and service follow-ups. Message frequency varies. Msg & data rates may apply. Reply HELP for help or STOP to opt out.';

    private const LIVE_MARKETING = 'I agree to receive marketing and promotional text messages from Copper Eagle Plumbing LLC, such as special offers, discounts, and seasonal maintenance reminders. Message frequency varies. Msg & data rates may apply. Reply HELP for help or STOP to opt out.';

    private const LIVE_NOTE = 'Both boxes are optional. Consent is not a condition of purchase, and you can submit this form without checking either box. See our Privacy Policy at coppereagleplumbing.com/privacy-policy and SMS Terms at coppereagleplumbing.com/terms-and-conditions.';

    protected function setUp(): void
    {
        parent::setUp();

        config(['website-forms.provider' => 'native']);
        RateLimiter::clear('quote-form:127.0.0.1');
    }

    public function test_the_site_is_configured_with_the_live_text_message_choices(): void
    {
        $this->assertSame('choices', LeadConsent::mode());
        $this->assertFalse(LeadConsent::isRequired());
        $this->assertSame(self::LIVE_TITLE, LeadConsent::choicesTitle());
        $this->assertSame(['service' => self::LIVE_SERVICE, 'marketing' => self::LIVE_MARKETING], LeadConsent::choices());

        // The registered sender is the business the site is named for.
        $this->assertStringContainsString('text messages from '.config('site.name').' LLC', self::LIVE_SERVICE);
        $this->assertStringContainsString('text messages from '.config('site.name').' LLC', self::LIVE_MARKETING);
    }

    public function test_native_inquiry_shows_two_separate_optional_unchecked_text_message_boxes(): void
    {
        $component = Livewire::test(QuoteForm::class, ['formId' => 'contact-form'])
            ->assertSet('consentService', false)
            ->assertSet('consentMarketing', false);

        $html = $component->html();
        $xpath = $this->xpath($html);

        $fieldset = $xpath->query('//fieldset[contains(concat(" ", @class, " "), " quote-consent-group ")]')->item(0);
        $this->assertNotNull($fieldset, 'The text-message choices are grouped in a fieldset.');
        $this->assertSame(self::LIVE_TITLE, $this->text($xpath->query('./legend', $fieldset)->item(0)));
        $this->assertSame(self::LIVE_NOTE, $this->text($xpath->query('.//p[@id="contact-form-consent-choices-note"]', $fieldset)->item(0)));

        foreach (['service' => self::LIVE_SERVICE, 'marketing' => self::LIVE_MARKETING] as $kind => $wording) {
            $boxes = $xpath->query('.//input[@type="checkbox"][@id="contact-form-consent-'.$kind.'"]', $fieldset);
            $this->assertCount(1, $boxes, "One {$kind} box.");
            $box = $boxes->item(0);

            $this->assertSame('consent'.ucfirst($kind), $box->getAttribute('wire:model'));
            $this->assertFalse($box->hasAttribute('required'), "The {$kind} box is optional.");
            $this->assertFalse($box->hasAttribute('checked'), "The {$kind} box starts unchecked.");
            $this->assertSame('contact-form-consent-choices-note', $box->getAttribute('aria-describedby'));

            $labels = $xpath->query('//label[@for="contact-form-consent-'.$kind.'"]');
            $this->assertCount(1, $labels);
            $this->assertSame($wording, $this->text($labels->item(0)));
        }

        // Both legal pages are linked beside the choices, not only in the footer.
        $this->assertCount(1, $xpath->query('.//a[@href="/privacy-policy/"]', $fieldset));
        $this->assertCount(1, $xpath->query('.//a[@href="/terms-and-conditions/"]', $fieldset));

        // One permission per box: no single catch-all box beside them.
        $this->assertStringNotContainsString('wire:model="consent"', $html);
        $this->assertCount(2, $xpath->query('//input[@type="checkbox"]'));
    }

    public function test_an_inquiry_can_be_sent_without_either_box_and_records_both_declines(): void
    {
        $lead = $this->submit();

        $this->assertFalse($lead->consent_given);
        $this->assertFalse($lead->consent_service);
        $this->assertFalse($lead->consent_marketing);
        $this->assertNull($lead->consent_at);
        $this->assertNull($lead->consent_ip);
        $this->assertSame(LeadConsent::evidenceText(), $lead->consent_text);
    }

    public function test_each_box_is_recorded_on_its_own(): void
    {
        $service = $this->submit(service: true);

        $this->assertTrue($service->consent_service);
        $this->assertFalse($service->consent_marketing);
        $this->assertTrue($service->consent_given);
        $this->assertNotNull($service->consent_at);
        $this->assertSame('127.0.0.1', $service->consent_ip);

        $marketing = $this->submit(marketing: true);

        $this->assertFalse($marketing->consent_service);
        $this->assertTrue($marketing->consent_marketing);

        $both = $this->submit(service: true, marketing: true);

        $this->assertTrue($both->consent_service);
        $this->assertTrue($both->consent_marketing);
    }

    public function test_the_stored_wording_is_the_complete_text_shown_beside_the_boxes(): void
    {
        $lead = $this->submit(service: true);

        $this->assertSame(
            implode("\n", [
                self::LIVE_TITLE,
                '- '.self::LIVE_SERVICE,
                '- '.self::LIVE_MARKETING,
                'Both boxes are optional. Consent is not a condition of purchase, and you can submit this form without checking either box. See our Privacy Policy at coppereagleplumbing.com/privacy-policy (/privacy-policy/) and SMS Terms at coppereagleplumbing.com/terms-and-conditions (/terms-and-conditions/).',
            ]),
            $lead->consent_text,
        );
    }

    public function test_the_boxes_reset_after_a_request_is_sent(): void
    {
        Livewire::test(QuoteForm::class)
            ->set('firstName', 'Taylor')
            ->set('lastName', 'Example')
            ->set('phone', '555 555 0100')
            ->set('consentService', true)
            ->set('consentMarketing', true)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('consentService', false)
            ->assertSet('consentMarketing', false);
    }

    public function test_the_form_does_not_promise_details_are_used_only_for_this_request(): void
    {
        // A visitor may opt in to marketing texts, so that sentence would be untrue.
        Livewire::test(QuoteForm::class)->assertDontSee('only use your details to answer this request');
    }

    public function test_the_default_footer_exposes_both_public_legal_pages(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('href="/privacy-policy/"', false)
            ->assertSee('href="/terms-and-conditions/"', false);
    }

    private function submit(bool $service = false, bool $marketing = false): Lead
    {
        RateLimiter::clear('quote-form:127.0.0.1');

        Livewire::test(QuoteForm::class)
            ->set('firstName', 'Taylor')
            ->set('lastName', 'Example')
            ->set('phone', '555 555 0100')
            ->set('consentService', $service)
            ->set('consentMarketing', $marketing)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('sent', true);

        return Lead::query()->latest('id')->firstOrFail();
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new \DOMXPath($dom);
    }

    private function text(?\DOMNode $node): string
    {
        $this->assertNotNull($node);

        return trim((string) preg_replace('/\s+/u', ' ', $node->textContent));
    }
}
