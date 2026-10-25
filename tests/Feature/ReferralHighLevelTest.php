<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Models\ReferralTeamMember;
use App\Services\Referrals\HighLevelClient;
use App\Services\Referrals\ReferralSync;
use App\Services\Referrals\TeamDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReferralHighLevelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Cache::flush();
        config(['referrals.service_areas' => ['North Springfield', 'South Springfield', 'Other']]);
        config(['referrals.highlevel' => ['token' => 'test-token', 'location_id' => 'location', 'company_id' => null,
            'excluded_user_ids' => ['jordan'], 'fields' => ['code' => 'field-code', 'source' => 'field-source', 'business' => 'field-business', 'main_contact' => 'field-owner', 'url' => 'field-url', 'service_areas' => 'field-areas']]]);
    }

    private function affiliate(): Referral
    {
        $r = Referral::create(['code' => 'abc123', 'first_name' => 'Taylor', 'last_name' => 'Example', 'phone' => '+15555550101', 'email' => 'taylor@example.test', 'service_areas' => ['North Springfield', 'South Springfield'], 'name' => 'Taylor Example', 'business_name' => 'Example Co.', 'main_contact_id' => 'casey', 'main_contact_name' => 'Casey Example', 'registered_at' => now()]);
        $r->forceFill(['highlevel_location_id' => 'location'])->save();

        return $r;
    }

    private function contactFields(array $overrides = []): array
    {
        $fields = [
            ['id' => 'auto-code', 'fieldKey' => 'contact.referral_code', 'dataType' => 'TEXT'],
            ['id' => 'auto-source', 'fieldKey' => 'contact.referral_source', 'dataType' => 'TEXT'],
            ['id' => 'auto-business', 'fieldKey' => 'contact.referral_business', 'dataType' => 'TEXT'],
            ['id' => 'auto-owner', 'fieldKey' => 'contact.internal_sales_person', 'dataType' => 'TEXT'],
            ['id' => 'auto-url', 'fieldKey' => 'contact.referral_url', 'dataType' => 'TEXT'],
            ['id' => 'auto-areas', 'fieldKey' => 'contact.service_areas', 'dataType' => 'MULTIPLE_OPTIONS', 'picklistOptions' => config('referrals.service_areas')],
        ];

        return array_map(fn ($field) => ['locationId' => 'location', ...$field], array_replace($fields, $overrides));
    }

    public function test_team_refresh_excludes_owner_deleted_and_foreign_location_users(): void
    {
        Http::fake(['*/users/*' => Http::response(['users' => [
            ['id' => 'casey', 'name' => 'Casey Example', 'roles' => ['locationIds' => ['location']]],
            ['id' => 'riley', 'firstName' => 'Riley', 'lastName' => 'Example'],
            ['id' => 'jordan', 'name' => 'Jordan Example'],
            ['id' => 'deleted', 'name' => 'Deleted', 'deleted' => true],
            ['id' => 'foreign', 'name' => 'Foreign', 'roles' => ['locationIds' => ['other']]],
        ]])]);
        $directory = app(TeamDirectory::class);
        $this->assertSame(2, $directory->refresh(app(HighLevelClient::class)));
        $this->assertSame(['casey', 'riley'], $directory->available()->pluck('highlevel_id')->all());
        Http::assertSent(fn ($r) => $r['locationId'] === 'location' && $r->hasHeader('Version', '2021-07-28'));
    }

    public function test_team_refresh_with_blank_exclusions_imports_all_active_location_users(): void
    {
        config(['referrals.highlevel.excluded_user_ids' => []]);
        ReferralTeamMember::create(['highlevel_id' => 'casey', 'location_id' => 'location', 'name' => 'Casey Example', 'active' => false]);
        Http::fake(['*/users/*' => Http::response(['users' => [
            ['id' => 'casey', 'name' => 'Casey Example', 'roles' => ['locationIds' => ['location']]],
            ['id' => 'riley', 'name' => 'Riley Example', 'roles' => ['locationIds' => ['location']]],
        ]])]);

        $directory = app(TeamDirectory::class);
        $this->assertSame(2, $directory->refresh(app(HighLevelClient::class)));
        $this->assertSame(['casey', 'riley'], $directory->available()->pluck('highlevel_id')->all());
    }

    public function test_directory_failure_preserves_previous_team_and_hides_provider_response(): void
    {
        ReferralTeamMember::create(['highlevel_id' => 'casey', 'location_id' => 'location', 'name' => 'Casey Example', 'active' => true]);
        Http::fake(['*' => Http::response(['secret' => 'private-provider-body'], 500)]);
        try {
            app(TeamDirectory::class)->refresh(app(HighLevelClient::class));
            $this->fail('Expected a directory error');
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString('private-provider-body', $e->getMessage());
        }
        $this->assertCount(1, app(TeamDirectory::class)->available());
    }

    public function test_contact_sync_sends_attribution_and_repeat_sync_does_not_create_again(): void
    {
        Http::fake(['*/contacts/' => Http::response(['contact' => ['id' => 'remote', 'locationId' => 'location']], 201)]);
        $r = $this->affiliate();
        $this->assertTrue(app(ReferralSync::class)->sync($r));
        $this->assertTrue(app(ReferralSync::class)->sync($r->fresh()));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['firstName'] === 'Taylor' && $request['lastName'] === 'Example'
            && $request['assignedTo'] === 'casey' && $request['companyName'] === 'Example Co.'
            && $request['phone'] === '+15555550101' && $request['email'] === 'taylor@example.test'
            && in_array(['id' => 'field-areas', 'fieldValue' => ['North Springfield', 'South Springfield']], $request['customFields'], true)
            && in_array(['id' => 'field-code', 'fieldValue' => 'abc123'], $request['customFields'], true)
            && in_array(['id' => 'field-source', 'fieldValue' => 'Taylor_Example'], $request['customFields'], true));
        $this->assertSame('synced', $r->fresh()->sync_status);
    }

    public function test_missing_field_ids_are_discovered_by_exact_contact_keys_before_creation(): void
    {
        config(['referrals.highlevel.fields' => array_fill_keys(['code', 'source', 'business', 'main_contact', 'url', 'service_areas'], null)]);
        Http::fake([
            '*/customFields*' => Http::response(['customFields' => $this->contactFields()]),
            '*/contacts/' => Http::response(['contact' => ['id' => 'remote', 'locationId' => 'location']], 201),
        ]);

        $this->assertTrue(app(ReferralSync::class)->sync($this->affiliate()));
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains($request->url(), '/locations/location/customFields?model=contact'));
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && in_array(['id' => 'auto-code', 'fieldValue' => 'abc123'], $request['customFields'], true)
            && in_array(['id' => 'auto-areas', 'fieldValue' => ['North Springfield', 'South Springfield']], $request['customFields'], true));

        $second = Referral::create([
            'code' => 'second-card', 'name' => 'Second Affiliate', 'first_name' => 'Second', 'last_name' => 'Affiliate',
            'business_name' => 'Second Business', 'main_contact_id' => 'casey', 'main_contact_name' => 'Casey Example',
            'service_areas' => ['Other'], 'registered_at' => now(),
        ]);
        $second->forceFill(['highlevel_location_id' => 'location'])->save();
        $this->assertTrue(app(ReferralSync::class)->sync($second));
        Http::assertSentCount(3);
        $this->assertCount(1, Http::recorded(fn ($request) => $request->method() === 'GET'));
    }

    public function test_wrong_service_area_options_keep_registration_pending_without_contact_creation(): void
    {
        config(['referrals.highlevel.fields.service_areas' => null]);
        Http::fake([
            '*/customFields*' => Http::sequence()
                ->push(['customFields' => $this->contactFields([
                    5 => ['id' => 'auto-areas', 'fieldKey' => 'contact.service_areas', 'dataType' => 'MULTIPLE_OPTIONS', 'picklistOptions' => ['Other']],
                ])])
                ->push(['customFields' => $this->contactFields()]),
            '*/contacts/' => Http::response(['contact' => ['id' => 'remote', 'locationId' => 'location']], 201),
        ]);

        $r = $this->affiliate();
        $this->assertFalse(app(ReferralSync::class)->sync($r));
        $this->assertSame('pending', $r->fresh()->sync_status);
        $this->assertStringContainsString('Service Areas', $r->fresh()->sync_message);
        Http::assertSent(fn ($request) => $request->method() === 'GET');
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
        $this->assertTrue(app(ReferralSync::class)->sync($r->fresh()));
        Http::assertSentCount(3);
    }

    public function test_registration_is_saved_before_highlevel_receives_all_contact_details(): void
    {
        ReferralTeamMember::create(['highlevel_id' => 'casey', 'location_id' => 'location', 'name' => 'Casey Example', 'active' => true]);
        $savedBeforeRequest = false;
        Http::fake(function ($request) use (&$savedBeforeRequest) {
            $saved = Referral::where('code', 'new-card')->first();
            $savedBeforeRequest = $saved?->registered_at !== null
                && $saved->email === 'taylor@example.test'
                && $saved->service_areas === ['North Springfield', 'South Springfield'];

            return Http::response(['contact' => ['id' => 'remote', 'locationId' => 'location']], 201);
        });

        $this->post('/referrals/new-card', [
            'first_name' => 'Taylor', 'last_name' => 'Example', 'phone' => '+15555550101',
            'email' => 'taylor@example.test', 'business_name' => 'Example Co.',
            'service_areas' => ['North Springfield', 'South Springfield'], 'main_contact_id' => 'casey',
        ])->assertRedirect('/referrals/new-card/welcome');

        $this->assertTrue($savedBeforeRequest);
        $this->assertSame('synced', Referral::where('code', 'new-card')->firstOrFail()->sync_status);
        Http::assertSent(fn ($request) => $request['phone'] === '+15555550101'
            && $request['email'] === 'taylor@example.test'
            && in_array(['id' => 'field-areas', 'fieldValue' => ['North Springfield', 'South Springfield']], $request['customFields'], true));
    }

    public function test_timeout_is_uncertain_and_retry_cannot_duplicate_a_contact(): void
    {
        Http::fake(fn () => throw new ConnectionException('secret-token provider data'));
        $r = $this->affiliate();
        $this->assertFalse(app(ReferralSync::class)->sync($r));
        $this->assertSame('uncertain', $r->fresh()->sync_status);
        $this->assertStringNotContainsString('secret-token', $r->fresh()->sync_message);
        Http::fake(fn () => throw new \LogicException('Must not retry ambiguous creation'));
        $this->assertFalse(app(ReferralSync::class)->sync($r->fresh()));
    }

    public function test_reconciliation_verifies_location_and_referral_code_before_linking(): void
    {
        $r = $this->affiliate();
        $r->forceFill(['sync_status' => 'uncertain'])->save();
        Http::fake(['*/contacts/remote' => Http::sequence()
            ->push(['contact' => ['id' => 'remote', 'locationId' => 'other', 'customFields' => [['id' => 'field-code', 'value' => 'abc123']]]])
            ->push(['contact' => ['id' => 'remote', 'locationId' => 'location', 'customFields' => [['id' => 'field-code', 'value' => 'abc123']]]])]);
        $this->assertFalse(app(ReferralSync::class)->reconcile($r, 'remote'));
        $this->assertNull($r->fresh()->highlevel_contact_id);
        $this->assertTrue(app(ReferralSync::class)->reconcile($r->fresh(), 'remote'));
        $this->assertSame('remote', $r->fresh()->highlevel_contact_id);
    }

    public function test_missing_field_configuration_and_location_changes_do_not_send(): void
    {
        $r = $this->affiliate();
        config(['referrals.highlevel.fields.code' => null]);
        Http::fake(['*/customFields*' => Http::response([], 403)]);
        $this->assertFalse(app(ReferralSync::class)->sync($r));
        $this->assertSame('pending', $r->fresh()->sync_status);
        config(['referrals.highlevel.fields.code' => 'field-code', 'referrals.highlevel.location_id' => 'other']);
        $this->assertFalse(app(ReferralSync::class)->sync($r->fresh()));
        Http::assertSent(fn ($request) => $request->method() === 'GET');
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_selected_service_areas_wait_when_field_discovery_is_unavailable(): void
    {
        $r = $this->affiliate();
        config(['referrals.highlevel.fields.service_areas' => null]);
        Http::fake(['*/customFields*' => Http::response([], 403)]);
        $this->assertFalse(app(ReferralSync::class)->sync($r));
        $this->assertSame('pending', $r->fresh()->sync_status);
        $this->assertStringContainsString('HighLevel contact fields are unavailable', $r->fresh()->sync_message);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_definite_rejection_is_retryable_but_server_failure_is_uncertain(): void
    {
        Http::fakeSequence()->push([], 401)->push([], 503);
        $r = $this->affiliate();
        app(ReferralSync::class)->sync($r);
        $this->assertSame('failed', $r->fresh()->sync_status);
        app(ReferralSync::class)->sync($r->fresh());
        $this->assertSame('uncertain', $r->fresh()->sync_status);
        Http::assertSentCount(2);
    }

    public function test_concurrent_sync_requests_only_send_one_create(): void
    {
        $r = $this->affiliate();
        Http::fake(function () use ($r) {
            $this->assertFalse(app(ReferralSync::class)->sync($r->fresh()));

            return Http::response(['contact' => ['id' => 'remote', 'locationId' => 'location']], 201);
        });
        $this->assertTrue(app(ReferralSync::class)->sync($r));
        Http::assertSentCount(1);
    }

    public function test_paginated_user_search_keeps_every_page(): void
    {
        config(['referrals.highlevel.company_id' => 'company']);
        $users = [];
        for ($i = 0; $i < 100; $i++) {
            $users[] = ['id' => 'member-'.$i, 'name' => 'Member '.$i];
        }
        Http::fake(['*/users/search*' => Http::sequence()->push(['users' => $users, 'count' => 101])->push(['users' => [['id' => 'last', 'name' => 'Last Member']], 'count' => 101])]);
        $this->assertSame(101, app(TeamDirectory::class)->refresh(app(HighLevelClient::class)));
        Http::assertSent(fn ($r) => $r['skip'] === 100 && $r['companyId'] === 'company' && $r['locationId'] === 'location');
    }

    public function test_user_search_follows_reported_total_when_page_is_shorter_than_requested(): void
    {
        config(['referrals.highlevel.company_id' => 'company']);
        Http::fake(['*/users/search*' => Http::sequence()
            ->push(['users' => [['id' => 'casey', 'name' => 'Casey Example'], ['id' => 'riley', 'name' => 'Riley Example']], 'count' => 3])
            ->push(['users' => [['id' => 'third', 'name' => 'Third']], 'count' => 3])]);

        $this->assertSame(3, app(TeamDirectory::class)->refresh(app(HighLevelClient::class)));
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['skip'] === 2 && $request['companyId'] === 'company');
    }

    public function test_incomplete_user_search_does_not_replace_saved_team(): void
    {
        config(['referrals.highlevel.company_id' => 'company']);
        ReferralTeamMember::create(['highlevel_id' => 'casey', 'location_id' => 'location', 'name' => 'Casey Example', 'active' => true]);
        Http::fake(['*/users/search*' => Http::sequence()
            ->push(['users' => [['id' => 'riley', 'name' => 'Riley Example']], 'count' => 2])
            ->push(['users' => [], 'count' => 2])]);

        try {
            app(TeamDirectory::class)->refresh(app(HighLevelClient::class));
            $this->fail('Expected incomplete pagination to fail');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('incomplete team list', $exception->getMessage());
        }
        $this->assertSame(['casey'], app(TeamDirectory::class)->available()->pluck('highlevel_id')->all());
    }

    public function test_late_timeout_cannot_overwrite_a_verified_reconciliation(): void
    {
        $r = $this->affiliate();
        $reconciliations = [];
        Http::fake([
            '*/contacts/remote' => Http::response(['contact' => ['id' => 'remote', 'locationId' => 'location', 'customFields' => [['id' => 'field-code', 'value' => 'abc123']]]]),
            '*/contacts/' => function () use ($r, &$reconciliations) {
                $reconciliations[] = app(ReferralSync::class)->reconcile($r->fresh(), 'remote');
                $this->travel(3)->minutes();
                $reconciliations[] = app(ReferralSync::class)->reconcile($r->fresh(), 'remote');
                throw new ConnectionException('Late timeout');
            },
        ]);
        app(ReferralSync::class)->sync($r);
        $this->assertSame([false, true], $reconciliations);
        $this->assertSame('synced', $r->fresh()->sync_status);
        $this->assertSame('remote', $r->fresh()->highlevel_contact_id);
    }
}
