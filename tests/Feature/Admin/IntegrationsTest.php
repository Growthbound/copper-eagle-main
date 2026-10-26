<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\IntegrationsEditor;
use App\Models\User;
use App\Support\Integrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IntegrationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        // Tracking loads only on the canonical host; the test host is it.
        config()->set('search.canonical_host', 'localhost');
    }

    public function test_an_unconfigured_site_loads_no_third_party_scripts(): void
    {
        $this->assertFalse(Integrations::anyConfigured());

        $this->get('/')
            ->assertOk()
            ->assertDontSee('googletagmanager.com', false)
            ->assertDontSee('clarity.ms', false)
            ->assertDontSee('connect.facebook.net', false);
    }

    public function test_a_saved_measurement_id_reaches_the_public_page(): void
    {
        Livewire::test(IntegrationsEditor::class)
            ->set('integrations.ga4_measurement_id', 'G-ABC1234567')
            ->call('save')
            ->assertHasNoErrors();

        $this->get('/')
            ->assertSee('G-ABC1234567', false)
            ->assertSee('googletagmanager.com/gtag/js', false);
    }

    public function test_clarity_and_meta_pixel_load_only_when_set(): void
    {
        Livewire::test(IntegrationsEditor::class)
            ->set('integrations.clarity_project_id', 'abcd1234ef')
            ->set('integrations.meta_pixel_id', '123456789012345')
            ->call('save')
            ->assertHasNoErrors();

        $this->get('/')
            ->assertSee('clarity.ms/tag/', false)
            ->assertSee('123456789012345', false);
    }

    public function test_a_malformed_id_is_rejected_with_the_expected_format(): void
    {
        Livewire::test(IntegrationsEditor::class)
            ->set('integrations.ga4_measurement_id', 'UA-12345-1')
            ->call('save')
            ->assertHasErrors('integrations.ga4_measurement_id');

        $this->assertNull(Integrations::get('ga4_measurement_id'));
    }

    public function test_custom_snippets_render_verbatim(): void
    {
        Livewire::test(IntegrationsEditor::class)
            ->set('integrations.body_snippet', '<script id="chat-widget"></script>')
            ->call('save')
            ->assertHasNoErrors();

        $this->get('/')->assertSee('<script id="chat-widget"></script>', false);
    }

    public function test_tracking_never_loads_in_the_admin(): void
    {
        Livewire::test(IntegrationsEditor::class)
            ->set('integrations.ga4_measurement_id', 'G-ABC1234567')
            ->call('save');

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('G-ABC1234567', false);
    }

    public function test_settings_persist_outside_the_codebase(): void
    {
        Livewire::test(IntegrationsEditor::class)
            ->set('integrations.clarity_project_id', 'abcd1234ef')
            ->call('save');

        $this->assertDatabaseHas('settings', [
            'key' => Integrations::PREFIX.'clarity_project_id',
            'value' => 'abcd1234ef',
        ]);
    }

    /**
     * A deployment can carry the IDs in its environment, so a fresh database
     * still tracks. Anything the owner saves in the admin wins.
     */
    public function test_environment_ids_apply_until_the_owner_saves_their_own(): void
    {
        config()->set('integrations.defaults.gtm_container_id', 'GTM-ENV1234');
        config()->set('integrations.defaults.clarity_project_id', 'not valid!');

        $this->get('/')
            ->assertSee("'GTM-ENV1234'", false)
            ->assertSee('googletagmanager.com/ns.html?id=GTM-ENV1234', false)
            ->assertDontSee('clarity.ms', false);

        Livewire::test(IntegrationsEditor::class)
            ->set('integrations.gtm_container_id', 'GTM-SAVED99')
            ->call('save')
            ->assertHasNoErrors();

        $this->get('/')
            ->assertSee("'GTM-SAVED99'", false)
            ->assertDontSee('GTM-ENV1234', false);
    }

    public function test_call_tracking_loads_its_account_script(): void
    {
        Livewire::test(IntegrationsEditor::class)
            ->set('integrations.ctm_account_id', '581672')
            ->call('save')
            ->assertHasNoErrors();

        $this->get('/')->assertSee('<script async src="https://581672.tctm.co/t.js"></script>', false);

        Livewire::test(IntegrationsEditor::class)
            ->set('integrations.ctm_account_id', '581672.evil.test/x')
            ->call('save')
            ->assertHasErrors('integrations.ctm_account_id');
    }

    /**
     * A staging copy runs with the production settings. Its visits must not
     * land in the client's analytics or call tracking.
     */
    public function test_tracking_stays_off_a_non_canonical_host(): void
    {
        Livewire::test(IntegrationsEditor::class)
            ->set('integrations.gtm_container_id', 'GTM-SAVED99')
            ->set('integrations.body_snippet', '<script id="chat-widget"></script>')
            ->call('save');

        config()->set('search.canonical_host', 'www.example.com');

        $this->get('/')
            ->assertOk()
            ->assertDontSee('GTM-SAVED99', false)
            ->assertDontSee('chat-widget', false);

        config()->set('integrations.canonical_host_only', false);

        $this->get('/')->assertSee('GTM-SAVED99', false);
    }
}
