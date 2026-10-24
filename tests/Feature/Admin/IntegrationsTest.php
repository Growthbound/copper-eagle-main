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
}
