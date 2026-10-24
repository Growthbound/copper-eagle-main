<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendWebhook;
use App\Livewire\Admin\Webhooks as WebhooksScreen;
use App\Models\Lead;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Webhooks\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create());
    }

    /**
     * The wiring itself: saving a lead schedules a delivery per active
     * destination, deferred so the visitor's request is not held open while a
     * third-party endpoint responds.
     */
    public function test_saving_a_lead_schedules_a_deferred_delivery_for_each_active_destination(): void
    {
        Bus::fake();

        $this->makeWebhook('CRM', 'https://crm.example/hook');
        $this->makeWebhook('Automation', 'https://zap.example/hook');
        $this->makeWebhook('Paused', 'https://paused.example/hook', active: false);

        Lead::query()->create(['name' => 'Taylor Example']);

        Bus::assertDispatchedAfterResponseTimes(SendWebhook::class, 2);
    }

    public function test_a_new_lead_is_sent_to_every_active_destination(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $crm = $this->makeWebhook('CRM', 'https://crm.example/hooks/leads');
        $automation = $this->makeWebhook('Automation', 'https://zap.example/hooks/leads');

        $this->createLeadAndDeliver();

        Http::assertSentCount(2);

        foreach ([$crm, $automation] as $webhook) {
            Http::assertSent(fn (Request $request): bool => $request->url() === $webhook->url);
        }
    }

    public function test_a_paused_destination_receives_nothing(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $this->makeWebhook('Paused', 'https://paused.example/hook', active: false);

        $this->createLeadAndDeliver();

        Http::assertNothingSent();
    }

    public function test_the_payload_carries_the_lead_and_the_event(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $this->makeWebhook('CRM', 'https://crm.example/hook');

        $this->createLeadAndDeliver([
            'name' => 'Taylor Example',
            'email' => 'taylor@example.test',
            'service' => 'Example Service',
        ]);

        Http::assertSent(function (Request $request): bool {
            $body = json_decode($request->body(), true);

            return $body['event'] === WebhookDispatcher::LEAD_CREATED
                && $body['data']['name'] === 'Taylor Example'
                && $body['data']['email'] === 'taylor@example.test'
                && $body['data']['service'] === 'Example Service'
                && $body['data']['status'] === 'new';
        });
    }

    public function test_the_request_is_signed_so_the_receiver_can_verify_it(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $webhook = $this->makeWebhook('CRM', 'https://crm.example/hook');

        $this->createLeadAndDeliver();

        Http::assertSent(function (Request $request) use ($webhook): bool {
            $timestamp = (int) $request->header(Webhook::TIMESTAMP_HEADER)[0];
            $expected = $webhook->sign($request->body(), $timestamp);

            return hash_equals($expected, $request->header(Webhook::SIGNATURE_HEADER)[0]);
        });
    }

    public function test_a_signature_computed_with_the_wrong_secret_does_not_match(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $webhook = $this->makeWebhook('CRM', 'https://crm.example/hook');

        $this->createLeadAndDeliver();

        Http::assertSent(function (Request $request): bool {
            $timestamp = (int) $request->header(Webhook::TIMESTAMP_HEADER)[0];

            $impostor = new Webhook(['secret' => 'whsec_not_the_real_secret']);

            return $impostor->sign($request->body(), $timestamp)
                !== $request->header(Webhook::SIGNATURE_HEADER)[0];
        });
    }

    public function test_a_rejected_delivery_is_retried_and_then_recorded_as_failed(): void
    {
        Http::fake(['*' => Http::response('nope', 500)]);

        $webhook = $this->makeWebhook('CRM', 'https://crm.example/hook');

        $this->createLeadAndDeliver();

        $delivery = WebhookDelivery::query()->sole();

        $this->assertFalse($delivery->succeeded);
        $this->assertSame(500, $delivery->status_code);
        $this->assertSame(3, $delivery->attempts);
        $this->assertNull($webhook->refresh()->last_delivered_at);
    }

    public function test_a_successful_delivery_is_recorded_without_retrying(): void
    {
        Http::fake(['*' => Http::response('', 202)]);

        $webhook = $this->makeWebhook('CRM', 'https://crm.example/hook');

        $this->createLeadAndDeliver();

        $delivery = WebhookDelivery::query()->sole();

        $this->assertTrue($delivery->succeeded);
        $this->assertSame(202, $delivery->status_code);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($webhook->refresh()->last_delivered_at);
    }

    public function test_a_failed_delivery_can_be_sent_again_from_the_admin(): void
    {
        // The endpoint is down for the initial delivery and its retries, then
        // recovers. A second Http::fake would not replace the first stub, so
        // the state lives in the closure instead.
        $endpointIsDown = true;

        Http::fake(function () use (&$endpointIsDown) {
            return Http::response('', $endpointIsDown ? 500 : 200);
        });

        $this->makeWebhook('CRM', 'https://crm.example/hook');
        $this->createLeadAndDeliver();

        $delivery = WebhookDelivery::query()->sole();
        $this->assertFalse($delivery->succeeded);

        $endpointIsDown = false;

        Livewire::test(WebhooksScreen::class)->call('redeliver', $delivery->id);

        $this->assertTrue(WebhookDelivery::query()->latest('id')->first()->succeeded);
        $this->assertSame(2, WebhookDelivery::query()->count());
    }

    public function test_a_test_delivery_can_be_sent_before_any_real_lead_arrives(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $webhook = $this->makeWebhook('CRM', 'https://crm.example/hook');

        Livewire::test(WebhooksScreen::class)->call('sendTest', $webhook->id);

        Http::assertSent(function (Request $request): bool {
            return json_decode($request->body(), true)['event'] === 'lead.test';
        });

        $this->assertTrue(WebhookDelivery::query()->sole()->succeeded);
    }

    public function test_a_destination_needs_a_full_address(): void
    {
        Livewire::test(WebhooksScreen::class)
            ->set('name', 'Broken')
            ->set('url', 'crm.example/hook')
            ->call('save')
            ->assertHasErrors('url');

        $this->assertSame(0, Webhook::query()->count());
    }

    public function test_each_destination_gets_its_own_secret(): void
    {
        $first = $this->makeWebhook('One', 'https://one.example/hook');
        $second = $this->makeWebhook('Two', 'https://two.example/hook');

        $this->assertNotSame($first->secret, $second->secret);
        $this->assertStringStartsWith('whsec_', $first->secret);
    }

    public function test_replacing_a_secret_changes_the_signature(): void
    {
        $webhook = $this->makeWebhook('CRM', 'https://crm.example/hook');
        $original = $webhook->secret;

        Livewire::test(WebhooksScreen::class)->call('rotateSecret', $webhook->id);

        $this->assertNotSame($original, $webhook->refresh()->secret);
    }

    public function test_guests_cannot_reach_the_webhook_screen(): void
    {
        auth()->logout();

        $this->get(route('admin.webhooks'))->assertRedirect(route('admin.login'));
    }

    private function makeWebhook(string $name, string $url, bool $active = true): Webhook
    {
        return Webhook::query()->create([
            'name' => $name,
            'url' => $url,
            'is_active' => $active,
        ]);
    }

    /**
     * Runs the delivery that production defers until after the response.
     *
     * The lead is created without model events so the automatic dispatch does
     * not also fire and double the counts these tests assert on; that wiring is
     * covered separately by the test above.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createLeadAndDeliver(array $attributes = []): Lead
    {
        $lead = Lead::withoutEvents(
            fn (): Lead => Lead::query()->create($attributes + ['name' => 'Taylor Example'])
        );

        Webhook::query()->where('is_active', true)->get()->each(
            fn (Webhook $webhook) => (new SendWebhook(
                $webhook->id,
                WebhookDispatcher::LEAD_CREATED,
                WebhookDispatcher::leadPayload($lead),
            ))->handle()
        );

        return $lead;
    }
}
