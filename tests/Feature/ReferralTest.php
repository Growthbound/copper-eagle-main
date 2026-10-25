<?php

namespace Tests\Feature;

use App\Content\RedirectValidator;
use App\Content\ReservedPublicPath;
use App\Models\Referral;
use App\Models\ReferralTeamMember;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReferralTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config([
            'referrals.form_url' => 'https://forms.example.com/referral',
            'referrals.service_areas' => ['North Springfield', 'South Springfield', 'East Springfield', 'Other'],
            'referrals.highlevel.location_id' => 'location',
            'referrals.highlevel.excluded_user_ids' => ['jordan'],
        ]);
        ReferralTeamMember::create(['highlevel_id' => 'casey', 'location_id' => 'location', 'name' => 'Casey Example', 'active' => true]);
        ReferralTeamMember::create(['highlevel_id' => 'jordan', 'location_id' => 'location', 'name' => 'Jordan Example', 'active' => true]);
    }

    private function details(array $overrides = []): array
    {
        return array_replace(['first_name' => 'Taylor', 'last_name' => 'Example', 'phone' => '+15555550101', 'email' => 'taylor@example.test', 'business_name' => 'A+B Supply', 'service_areas' => ['North Springfield', 'South Springfield'], 'main_contact_id' => 'casey'], $overrides);
    }

    public function test_unused_links_show_split_names_and_only_eligible_contacts(): void
    {
        $this->get('/referrals/Partner_123')->assertOk()->assertSee('Welcome to the '.config('site.name').' Affiliate Program')
            ->assertSee('name="first_name"', false)->assertSee('name="last_name"', false)
            ->assertSee('name="phone"', false)->assertSee('name="email"', false)
            ->assertSee('name="service_areas[]"', false)->assertSee('East Springfield')
            ->assertSee(config('site.name').' main contact')->assertSee('Casey Example')->assertDontSee('Jordan Example');
        $this->assertDatabaseCount('referrals', 1);
        $this->assertDatabaseHas('referrals', ['code' => 'partner_123', 'registered_at' => null, 'visits' => 1]);
    }

    public function test_referral_paths_cannot_be_claimed_by_managed_redirects_or_pages(): void
    {
        $this->assertTrue(ReservedPublicPath::isReserved('referrals/partner'));
        $this->assertArrayHasKey('/source_path', app(RedirectValidator::class)->validate([
            'source_path' => '/referrals/partner',
            'target' => '/services',
            'status_code' => 301,
            'enabled' => true,
        ])->errors());
    }

    public function test_registration_claims_inventory_and_shows_a_welcome_page(): void
    {
        $this->get('/referrals/Partner_123');
        $this->post('/referrals/Partner_123', $this->details(['first_name' => '  Taylor  ', 'code' => 'other']))
            ->assertRedirect('/referrals/partner_123/welcome');
        $this->assertDatabaseCount('referrals', 1);
        $this->assertDatabaseHas('referrals', ['code' => 'partner_123', 'first_name' => 'Taylor', 'last_name' => 'Example', 'main_contact_id' => 'casey', 'main_contact_name' => 'Casey Example', 'sync_status' => 'pending']);
        $referral = Referral::where('code', 'partner_123')->firstOrFail();
        $this->assertSame('+15555550101', $referral->phone);
        $this->assertSame('taylor@example.test', $referral->email);
        $this->assertSame(['North Springfield', 'South Springfield'], $referral->service_areas);
        $this->get('/referrals/partner_123/welcome')->assertOk()->assertSee('Your referral link is ready')->assertSee('/referrals/partner_123');
    }

    public function test_redirect_uses_saved_names_preserves_destination_query_and_rejects_visitor_overrides(): void
    {
        config(['referrals.form_url' => 'https://forms.example.com/referral?theme=light&refferral_source=old#form']);
        $this->post('/referrals/partner', $this->details());
        $this->get('/referrals/PARTNER?refferral_source=attacker&redirect=https://evil.test')
            ->assertRedirect('https://forms.example.com/referral?theme=light&refferral_source=Taylor_Example&refferral_business=A%2BB%20Supply#form');
        $this->assertDatabaseHas('referrals', ['code' => 'partner', 'visits' => 1]);
    }

    public function test_repeated_posts_never_overwrite_claim_or_count_as_visits(): void
    {
        $this->post('/referrals/partner', $this->details());
        $this->post('/referrals/PARTNER', $this->details(['first_name' => 'Other']))->assertRedirect();
        $this->assertDatabaseCount('referrals', 1);
        $this->assertDatabaseHas('referrals', ['first_name' => 'Taylor', 'visits' => 0]);
    }

    public function test_head_requests_do_not_inflate_visits(): void
    {
        $this->get('/referrals/partner');
        $this->head('/referrals/partner')->assertOk();
        $this->assertDatabaseHas('referrals', ['code' => 'partner', 'visits' => 1]);
    }

    public function test_invalid_or_excluded_contacts_and_malformed_fields_cannot_claim(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        foreach ([['main_contact_id' => 'jordan'], ['main_contact_id' => 'unknown'], ['first_name' => ['invalid']], ['last_name' => ' '], ['business_name' => str_repeat('a', 256)], ['phone' => ''], ['email' => 'bad'], ['service_areas' => ['Unknown Area']], ['service_areas' => ['North Springfield', 'North Springfield']]] as $index => $bad) {
            $url = '/referrals/invalid-'.$index;
            $errorKey = $index === 7 ? 'service_areas.0' : ($index === 8 ? 'service_areas.1' : array_key_first($bad));
            $response = $this->from($url)->post($url, $this->details($bad));
            $this->assertTrue(session()->has('errors'), 'Expected validation errors for case '.$index);
            $response->assertSessionHasErrors($errorKey);
            $this->get($url)->assertOk();
        }
        $this->assertSame(0, Referral::whereNotNull('registered_at')->count());
    }

    public function test_no_directory_shows_an_honest_unavailable_message_and_cannot_register(): void
    {
        ReferralTeamMember::query()->delete();
        $this->get('/referrals/free')->assertOk()->assertSee('Registration is temporarily unavailable');
        $this->post('/referrals/free', $this->details())->assertSessionHasErrors('main_contact_id');
        $this->assertSame(0, Referral::whereNotNull('registered_at')->count());
    }

    public function test_blank_exclusions_allow_every_active_contact_to_register(): void
    {
        config(['referrals.highlevel.excluded_user_ids' => []]);

        $this->get('/referrals/open')->assertOk()->assertSee('Casey Example')->assertSee('Jordan Example');
        $this->post('/referrals/open', $this->details(['main_contact_id' => 'jordan']))
            ->assertRedirect('/referrals/open/welcome');
        $this->assertDatabaseHas('referrals', ['code' => 'open', 'main_contact_id' => 'jordan']);
    }

    public function test_invalid_codes_and_direct_welcome_access_are_rejected(): void
    {
        foreach (['bad.code', 'bad%20code', str_repeat('a', 65)] as $code) {
            $this->get('/referrals/'.$code)->assertNotFound();
            $this->post('/referrals/'.$code, $this->details())->assertNotFound();
        }
        $this->get('/referrals/unclaimed/welcome')->assertNotFound();
    }

    public function test_existing_registrations_keep_their_name_and_redirect(): void
    {
        Referral::create(['code' => 'legacy', 'name' => 'Original Person', 'business_name' => 'Legacy Co', 'registered_at' => now()]);
        $this->get('/referrals/legacy')->assertRedirect('https://forms.example.com/referral?refferral_source=Original_Person&refferral_business=Legacy%20Co');
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/referrals/code-'.$i, $this->details())->assertRedirect();
        }
        $this->post('/referrals/too-many', $this->details())->assertTooManyRequests();
        $this->assertSame(5, Referral::whereNotNull('registered_at')->count());
    }

    public function test_competing_inventory_claim_keeps_the_first_affiliate(): void
    {
        $this->get('/referrals/race');
        $competed = false;
        DB::connection()->beforeExecuting(function ($query) use (&$competed): void {
            if (! $competed && str_starts_with($query, 'update "referrals" set "first_name"')) {
                $competed = true;
                DB::table('referrals')->where('code', 'race')->update([
                    'name' => 'First Winner', 'first_name' => 'First', 'last_name' => 'Winner',
                    'business_name' => 'First Business', 'registered_at' => now(),
                ]);
            }
        });
        $this->post('/referrals/race', $this->details())
            ->assertRedirect('https://forms.example.com/referral?refferral_source=First_Winner&refferral_business=First%20Business');
        $this->assertTrue($competed);
        $this->assertDatabaseHas('referrals', ['code' => 'race', 'name' => 'First Winner']);
        $this->assertDatabaseCount('referrals', 1);
    }

    public function test_migration_preserves_real_first_version_rows(): void
    {
        $migration = require database_path('migrations/2026_09_24_000001_expand_referral_program.php');
        $migration->down();
        DB::table('referrals')->insert([
            'code' => 'old-card', 'name' => 'Casey Ann Example', 'business_name' => 'Original Company',
            'created_at' => '2026-09-23 10:00:00', 'updated_at' => '2026-09-23 10:00:00',
        ]);
        $migration->up();
        $this->assertDatabaseHas('referrals', ['code' => 'old-card', 'name' => 'Casey Ann Example', 'registered_at' => '2026-09-23 10:00:00']);
        $this->get('/referrals/old-card')->assertRedirect('https://forms.example.com/referral?refferral_source=Casey_Ann_Example&refferral_business=Original%20Company');
    }

    public function test_contact_details_migration_preserves_existing_registrations(): void
    {
        $migration = require database_path('migrations/2026_10_01_000001_add_affiliate_contact_details_to_referrals.php');
        $this->assertTrue(Schema::hasColumn('referrals', 'phone'), 'New migration should already be applied by DatabaseMigrations.');
        $migration->down();
        DB::table('referrals')->insert([
            'code' => 'existing-card', 'name' => 'Existing Affiliate', 'business_name' => 'Existing Business',
            'registered_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $migration->up();

        $this->assertTrue(Schema::hasColumns('referrals', ['phone', 'email', 'service_areas']));
        $this->assertDatabaseHas('referrals', ['code' => 'existing-card', 'name' => 'Existing Affiliate', 'phone' => null]);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('referrals', 'phone'));
        $this->assertDatabaseHas('referrals', ['code' => 'existing-card', 'name' => 'Existing Affiliate']);
        $migration->up();
    }
}
