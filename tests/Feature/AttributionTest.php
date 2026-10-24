<?php

namespace Tests\Feature;

use App\Livewire\Admin\Leads;
use App\Livewire\QuoteForm;
use App\Models\Lead;
use App\Models\User;
use App\Webhooks\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class AttributionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('quote-form:127.0.0.1');
    }

    public function test_campaign_parameters_are_recorded_on_the_lead(): void
    {
        $this->get('/?utm_source=google&utm_medium=cpc&utm_campaign=spring-sale&utm_term=example+service');

        $lead = $this->submitForm();

        $this->assertSame('google', $lead->utm_source);
        $this->assertSame('cpc', $lead->utm_medium);
        $this->assertSame('spring-sale', $lead->utm_campaign);
        $this->assertSame('example service', $lead->utm_term);
    }

    public function test_an_advertising_click_identifier_is_recorded(): void
    {
        $this->get('/?gclid=Cj0KCQiA1234567890');

        $lead = $this->submitForm();

        $this->assertSame('Cj0KCQiA1234567890', $lead->gclid);
        $this->assertSame('Google Ads', $lead->source());
    }

    public function test_the_first_touch_survives_browsing_the_site(): void
    {
        $this->get('/?utm_source=google&utm_medium=cpc&utm_campaign=spring-sale');

        // Wandering the site must not overwrite what brought them here.
        $this->get('/');
        $this->get('/');

        $lead = $this->submitForm();

        $this->assertSame('google', $lead->utm_source);
        $this->assertSame('spring-sale', $lead->utm_campaign);
    }

    public function test_a_campaign_is_adopted_when_the_visit_began_without_one(): void
    {
        $this->get('/');
        $this->get('/?utm_source=newsletter&utm_medium=email');

        $lead = $this->submitForm();

        $this->assertSame('newsletter', $lead->utm_source);
    }

    public function test_the_landing_page_is_recorded_even_for_a_direct_visit(): void
    {
        $this->get('/');

        $lead = $this->submitForm();

        $this->assertStringContainsString('/', (string) $lead->landing_page);
        $this->assertNull($lead->utm_source);
        $this->assertSame('Direct', $lead->source());
    }

    public function test_an_offsite_referrer_is_recorded_and_described(): void
    {
        $this->get('/', ['referer' => 'https://www.google.com/search?q=example+service']);

        $lead = $this->submitForm();

        $this->assertStringContainsString('google.com', (string) $lead->referrer);
        $this->assertSame('Referral from www.google.com', $lead->source());
    }

    public function test_internal_navigation_is_not_treated_as_a_referral(): void
    {
        $this->get('/', ['referer' => config('app.url').'/some-page']);

        $lead = $this->submitForm();

        $this->assertNull($lead->referrer);
        $this->assertSame('Direct', $lead->source());
    }

    public function test_a_utm_source_is_described_with_its_medium(): void
    {
        $this->get('/?utm_source=facebook&utm_medium=social');

        $this->assertSame('facebook / social', $this->submitForm()->source());
    }

    public function test_browsing_the_admin_does_not_overwrite_a_visitor_journey(): void
    {
        $this->get('/?utm_source=google&utm_medium=cpc');

        $this->get('/admin/login');

        $lead = $this->submitForm();

        $this->assertSame('google', $lead->utm_source);
    }

    public function test_attribution_travels_with_the_webhook_payload(): void
    {
        $this->get('/?utm_source=google&utm_campaign=spring-sale&gclid=abc123');

        $lead = $this->submitForm();

        $payload = WebhookDispatcher::leadPayload($lead);

        $this->assertSame('Google Ads', $payload['source']);
        $this->assertSame('spring-sale', $payload['attribution']['utm_campaign']);
        $this->assertSame('abc123', $payload['attribution']['gclid']);
    }

    public function test_the_export_carries_the_campaign_columns(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/?utm_source=google&utm_campaign=spring-sale');
        $this->submitForm();

        $download = Livewire::test(Leads::class)->instance()->export();

        ob_start();
        $download->sendContent();
        $csv = (string) ob_get_clean();

        $this->assertStringContainsString('UTM campaign', $csv);
        $this->assertStringContainsString('spring-sale', $csv);
    }

    public function test_the_inbox_shows_where_an_enquiry_came_from(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/?utm_source=google&utm_medium=cpc&utm_campaign=spring-sale');
        $this->submitForm();

        Livewire::test(Leads::class)
            ->assertSee('google / cpc')
            ->assertSee('spring-sale');
    }

    public function test_a_long_parameter_cannot_overflow_its_column(): void
    {
        $this->get('/?utm_campaign='.str_repeat('a', 400));

        $this->assertSame(255, mb_strlen((string) $this->submitForm()->utm_campaign));
    }

    private function submitForm(): Lead
    {
        Livewire::test(QuoteForm::class)
            ->set('name', 'Taylor Example')
            ->set('phone', '555 555 0100')
            ->call('submit')
            ->assertHasNoErrors();

        return Lead::query()->latest('id')->firstOrFail();
    }
}
