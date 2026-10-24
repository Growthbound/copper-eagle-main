<?php

namespace Tests\Feature;

use App\HighLevel\LeadSync;
use App\Jobs\SyncLeadToHighLevel;
use App\Livewire\Admin\HighLevelConnection as ConnectionScreen;
use App\Livewire\Admin\Leads as LeadsScreen;
use App\Livewire\QuoteForm;
use App\Models\HighLevelConnection;
use App\Models\HighLevelDelivery;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class HighLevelLeadSyncTest extends TestCase
{
    use RefreshDatabase;

    private const LOCATION = 'locationExample123';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Bus::fake();
        $this->actingAs(User::factory()->create());
    }

    public function test_connect_verifies_location_encrypts_key_and_never_hydrates_saved_key(): void
    {
        Http::fake(['*' => Http::response(['location' => ['id' => self::LOCATION, 'name' => 'Example Service']], 200)]);

        Livewire::test(ConnectionScreen::class)
            ->set('locationId', self::LOCATION)->set('apiKey', 'private-test-token')
            ->call('connect')->assertHasNoErrors()->assertSet('apiKey', '')
            ->assertSee('Example Service')->assertDontSee('private-test-token');

        $connection = HighLevelConnection::query()->sole();
        $this->assertTrue($connection->enabled);
        $this->assertSame('private-test-token', $connection->token);
        $this->assertStringNotContainsString('private-test-token', DB::table('high_level_connections')->value('token'));
        $this->assertArrayNotHasKey('token', $connection->toArray());
        Livewire::test(ConnectionScreen::class)->assertSet('apiKey', '')->assertDontSee('private-test-token');
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r->hasHeader('Version', 'v3'));
    }

    public function test_bad_credentials_or_mismatched_location_do_not_replace_connection_or_expose_provider_body(): void
    {
        $connection = $this->connection();
        Http::fake(['*' => Http::response(['message' => 'secret-provider-body'], 401)]);
        Livewire::test(ConnectionScreen::class)->set('locationId', self::LOCATION)->set('apiKey', 'new-test-token')
            ->call('connect')->assertHasErrors('apiKey')->assertSet('apiKey', '')->assertDontSee('secret-provider-body');
        $this->assertSame('private-test-token', $connection->refresh()->token);
    }

    public function test_new_lead_has_durable_delivery_before_deferred_dispatch(): void
    {
        $this->connection();
        $lead = Lead::query()->create(['name' => 'Example Person', 'email' => 'example@example.test']);
        $this->assertSame($lead->id, HighLevelDelivery::query()->sole()->lead_id);
        Bus::assertDispatchedAfterResponse(SyncLeadToHighLevel::class);
        Http::assertNothingSent();
    }

    public function test_disabled_connection_keeps_native_submission_and_paused_delivery(): void
    {
        $this->connection()->update(['enabled' => false]);
        $lead = Lead::query()->create(['name' => 'Example Person', 'phone' => '8015550100']);
        $delivery = HighLevelDelivery::query()->sole();
        app(LeadSync::class)->send($delivery->id);
        $this->assertNotNull($lead->fresh());
        $this->assertSame('pending', $delivery->refresh()->status);
        Bus::assertNotDispatched(SyncLeadToHighLevel::class);
        Http::assertNothingSent();
    }

    public function test_success_adds_tag_without_replacing_existing_tags_and_preserves_full_attribution(): void
    {
        $this->connection();
        $this->fakeSuccess();
        $lead = $this->lead();
        $delivery = app(LeadSync::class)->enqueue($lead);
        app(LeadSync::class)->send($delivery->id);
        $this->assertSame('synced', $delivery->fresh()->status);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/contacts/upsert')
            && $r['locationId'] === self::LOCATION && $r['source'] === 'Referral from search.example'
            && $r['createNewIfDuplicateAllowed'] === false && ! isset($r['tags']) && ! isset($r['dnd']));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/contactExample/tags') && $r['tags'] === ['Website Lead']);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/contactExample/notes')
            && str_contains($r['body'], 'https://search.example/') && str_contains($r['body'], 'utm_campaign: example-campaign')
            && str_contains($r['body'], 'Please call about the vents.') && str_contains($r['body'], 'Consent: agreed'));
        app(LeadSync::class)->send($delivery->id);
        Http::assertSentCount(3);
    }

    public function test_tag_failure_retries_from_saved_contact_id_and_not_upsert(): void
    {
        $this->connection();
        $tagDown = true;
        $this->fakeSuccess(function (Request $r) use (&$tagDown) {
            return str_ends_with($r->url(), '/tags') && $tagDown ? Http::response([], 429) : null;
        });
        $delivery = app(LeadSync::class)->enqueue($this->lead());
        app(LeadSync::class)->send($delivery->id);
        $this->assertSame('pending', $delivery->refresh()->status);
        $this->assertSame('contactExample', $delivery->contact_id);
        $tagDown = false;
        $this->travel(10)->minutes();
        $this->artisan('highlevel:sync-leads')->assertSuccessful();
        $this->assertSame('synced', $delivery->fresh()->status);
        $upserts = Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/upsert'));
        $this->assertCount(1, $upserts);
    }

    public function test_wrong_location_response_blocks_tags_and_notes(): void
    {
        $this->connection();
        Http::fake(['*' => Http::response(['contact' => ['id' => 'otherContact', 'locationId' => 'wrongLocation']], 200)]);
        $delivery = app(LeadSync::class)->enqueue($this->lead());
        app(LeadSync::class)->send($delivery->id);
        $this->assertSame('failed', $delivery->refresh()->status);
        $this->assertNull($delivery->contact_id);
        Http::assertSentCount(1);
    }

    public function test_ambiguous_note_is_not_reposted_and_positive_readback_recovers_it(): void
    {
        $this->connection();
        $body = null;
        $this->fakeSuccess(function (Request $r) use (&$body) {
            if ($r->method() === 'POST' && str_ends_with($r->url(), '/notes')) {
                $body = $r['body'];

                return Http::response([], 503);
            }
            if ($r->method() === 'GET' && str_ends_with($r->url(), '/notes')) {
                return Http::response(['notes' => [['id' => 'noteExample', 'body' => $body, 'contactId' => 'contactExample']]], 200);
            }

            return null;
        });
        $delivery = app(LeadSync::class)->enqueue($this->lead());
        app(LeadSync::class)->send($delivery->id);
        $this->assertSame('needs_review', $delivery->fresh()->status);
        $this->travel(10)->minutes();
        $this->artisan('highlevel:sync-leads')->assertSuccessful();
        $this->assertSame('needs_review', $delivery->fresh()->status);
        app(LeadSync::class)->reconcile($delivery->id);
        $this->assertSame('synced', $delivery->fresh()->status);
        $this->assertCount(1, Http::recorded(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/notes')));
    }

    public function test_expired_note_worker_requires_review_and_overlapping_worker_does_nothing(): void
    {
        $this->connection();
        $delivery = app(LeadSync::class)->enqueue($this->lead());
        $delivery->update(['worker_token' => 'other-worker', 'locked_until' => now()->addMinutes(5)]);
        app(LeadSync::class)->send($delivery->id);
        Http::assertNothingSent();
        $delivery->update(['locked_until' => now()->subMinute(), 'note_started_at' => now()->subMinutes(6), 'contact_id' => 'contactExample']);
        app(LeadSync::class)->send($delivery->id);
        $this->assertSame('needs_review', $delivery->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_delivery_pins_account_and_deleted_lead_is_never_resurrected(): void
    {
        $this->connection();
        $lead = $this->lead();
        app(LeadSync::class)->enqueue($lead);
        Livewire::test(ConnectionScreen::class)->set('locationId', 'differentLocation')->set('apiKey', 'other-token')
            ->call('connect')->assertHasErrors('locationId')->assertSet('apiKey', '');
        Http::assertNothingSent();
        $lead->delete();
        $this->assertSame(0, HighLevelDelivery::query()->count());
        $this->artisan('highlevel:sync-leads')->assertSuccessful();
        $this->assertSame(0, Lead::query()->count());
    }

    public function test_auth_failure_is_visible_sanitized_and_not_automatically_retried(): void
    {
        $this->connection();
        Http::fake(['*' => Http::response(['message' => 'Bearer private-test-token example@example.test'], 403)]);
        $delivery = app(LeadSync::class)->enqueue($this->lead());
        app(LeadSync::class)->send($delivery->id);
        $this->assertSame('failed', $delivery->refresh()->status);
        $this->assertStringNotContainsString('private-test-token', $delivery->error);
        $this->assertStringNotContainsString('example@example.test', $delivery->error);
        $this->travel(10)->minutes();
        $this->artisan('highlevel:sync-leads')->assertSuccessful();
        Http::assertSentCount(1);
    }

    public function test_saved_leads_are_only_backfilled_individually_and_guests_cannot_access_settings(): void
    {
        $this->connection();
        $this->fakeSuccess();
        $lead = $this->lead();
        $other = $this->lead();
        $this->assertSame(0, HighLevelDelivery::query()->count());
        Livewire::test(LeadsScreen::class)->call('syncHighLevel', $lead->id)->assertSee('synced');
        $this->assertSame(1, HighLevelDelivery::query()->count());
        $this->assertNull($other->fresh()->highLevelDelivery);
        auth()->logout();
        $this->get(route('admin.webhooks'))->assertRedirect(route('admin.login'));
    }

    private function connection(): HighLevelConnection
    {
        return HighLevelConnection::query()->create(['id' => 1, 'location_id' => self::LOCATION, 'location_name' => 'Example Service', 'token' => 'private-test-token', 'enabled' => true]);
    }

    public function test_failed_note_readback_keeps_ambiguity_and_never_creates_another_note(): void
    {
        $this->connection();
        $this->fakeSuccess(fn (Request $r) => str_ends_with($r->url(), '/notes') ? Http::response([], 503) : null);
        $delivery = app(LeadSync::class)->enqueue($this->lead());
        app(LeadSync::class)->send($delivery->id);
        app(LeadSync::class)->reconcile($delivery->id);
        app(LeadSync::class)->send($delivery->id, manual: true);
        $this->assertSame('needs_review', $delivery->refresh()->status);
        $this->assertNotNull($delivery->note_started_at);
        $this->assertCount(1, Http::recorded(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/notes')));
    }

    public function test_explicit_note_rejection_allows_safe_retry_from_completed_stages(): void
    {
        $this->connection();
        $reject = true;
        $this->fakeSuccess(fn (Request $r) => str_ends_with($r->url(), '/notes') && $reject ? Http::response([], 422) : null);
        $delivery = app(LeadSync::class)->enqueue($this->lead());
        app(LeadSync::class)->send($delivery->id);
        $this->assertSame('failed', $delivery->refresh()->status);
        $this->assertNull($delivery->note_started_at);
        $this->assertNotNull($delivery->tagged_at);
    }

    public function test_transport_failure_retries_safe_upsert_with_duplicate_creation_disabled(): void
    {
        $this->connection();
        Http::fake(['*' => Http::failedConnection('contains-private-test-token')]);
        $delivery = app(LeadSync::class)->enqueue($this->lead());
        app(LeadSync::class)->send($delivery->id);
        $this->assertSame('pending', $delivery->refresh()->status);
        $this->assertStringNotContainsString('private-test-token', $delivery->error);
        $this->assertNull($delivery->contact_id);
    }

    public function test_retry_limit_stops_and_reconnect_does_not_backfill_historical_leads(): void
    {
        $this->connection();
        Http::fake(['*' => Http::response([], 429)]);
        $delivery = app(LeadSync::class)->enqueue($this->lead());
        $other = $this->lead();
        for ($i = 0; $i < 5; $i++) {
            app(LeadSync::class)->send($delivery->id);
            $this->travel(60)->minutes();
        }
        $this->assertSame('failed', $delivery->refresh()->status);
        $this->assertSame(5, $delivery->attempts);
        $this->assertNull($other->fresh()->highLevelDelivery);
        Http::assertSentCount(5);
    }

    public function test_pause_disconnect_and_stale_tab_conflict_preserve_account_history(): void
    {
        $connection = $this->connection();
        $stale = Livewire::test(ConnectionScreen::class);
        $current = Livewire::test(ConnectionScreen::class)->call('pause');
        $this->assertFalse($connection->refresh()->enabled);
        $this->assertSame('private-test-token', $connection->token);
        $stale->call('disconnect')->assertHasErrors('locationId');
        $current->call('disconnect');
        $this->assertNull($connection->refresh()->token);
        $this->assertSame(self::LOCATION, $connection->location_id);
    }

    public function test_success_shape_and_location_verification_fail_closed(): void
    {
        Http::fake(['*' => Http::response(['location' => ['id' => 'wrongLocation', 'name' => 'Wrong account']], 200)]);
        Livewire::test(ConnectionScreen::class)->set('locationId', self::LOCATION)->set('apiKey', 'private-test-token')
            ->call('connect')->assertHasErrors('apiKey')->assertSet('apiKey', '');
        $this->assertSame(0, HighLevelConnection::query()->count());
    }

    public function test_real_quote_submission_wires_direct_delivery_without_blocking_visitor(): void
    {
        $this->connection();
        Livewire::test(QuoteForm::class)->set('name', 'Example Person')->set('phone', '8015550100')
            ->call('submit')->assertHasNoErrors()->assertSet('sent', true);
        $this->assertSame('pending', HighLevelDelivery::query()->sole()->status);
        Bus::assertDispatchedAfterResponse(SyncLeadToHighLevel::class);
        Http::assertNothingSent();
    }

    private function lead(): Lead
    {
        return Lead::withoutEvents(function () {
            $lead = new Lead(['name' => 'Example Person', 'email' => 'example@example.test', 'message' => 'Please call about the vents.', 'consent_given' => true]);
            $lead->forceFill(['referrer' => 'https://search.example/', 'landing_page' => 'https://example.test/service', 'utm_campaign' => 'example-campaign'])->save();

            return $lead;
        });
    }

    private function fakeSuccess(?callable $override = null): void
    {
        Http::fake(function (Request $r) use ($override) {
            if ($response = $override ? $override($r) : null) {
                return $response;
            }
            if (str_ends_with($r->url(), '/upsert') || ($r->method() === 'GET' && str_ends_with($r->url(), '/contactExample'))) {
                return Http::response(['contact' => ['id' => 'contactExample', 'locationId' => self::LOCATION]], 200);
            }
            if (str_ends_with($r->url(), '/tags')) {
                return Http::response(['tags' => ['Existing Tag', 'Website Lead']], 201);
            }
            if (str_ends_with($r->url(), '/notes')) {
                return Http::response(['note' => ['id' => 'noteExample', 'contactId' => 'contactExample']], 201);
            }

            return Http::response([], 404);
        });
    }
}
