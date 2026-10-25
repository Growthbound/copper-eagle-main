<?php

namespace Tests\Feature\Admin;

use App\Enums\MediaKind;
use App\Livewire\Admin\SiteDetailsEditor;
use App\Models\Media;
use App\Models\User;
use App\Support\SiteDetails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiteDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_saved_contact_details_appear_on_the_public_site(): void
    {
        Livewire::test(SiteDetailsEditor::class)
            ->set('details.phone', '+1 555 555 0100')
            ->set('details.email', 'hello@example.test')
            ->set('details.address', '123 Example Street, Springfield')
            ->call('save')
            ->assertHasNoErrors();

        $this->get('/')
            // The call component strips formatting so the href is dialable.
            ->assertSee('href="tel:+15555550100"', false)
            ->assertSee('href="mailto:hello@example.test"', false)
            ->assertSee('123 Example Street, Springfield');
    }

    public function test_a_malformed_email_is_rejected(): void
    {
        Livewire::test(SiteDetailsEditor::class)
            ->set('details.email', 'not-an-address')
            ->call('save')
            ->assertHasErrors('details.email');
    }

    public function test_business_name_and_logo_are_owner_editable(): void
    {
        $logo = Media::query()->create([
            'path' => 'media/acme-logo.png',
            'original_name' => 'acme-logo.png',
            'mime_type' => 'image/png',
            'size' => 2048,
            'width' => 480,
            'height' => 160,
            'alt' => 'Acme Home Services',
        ]);

        Livewire::test(SiteDetailsEditor::class)
            ->set('details.name', 'Acme Home Services')
            ->set('details.logo_media_id', (string) $logo->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->get('/')
            ->assertSee('aria-label="Acme Home Services home"', false)
            ->assertSee($logo->url(), false)
            ->assertSee('alt="Acme Home Services"', false)
            // The chosen logo replaces the text wordmark.
            ->assertDontSee('<span>Acme Home Services</span>', false);
    }

    public function test_brand_mark_uses_the_configured_wordmark_when_no_logo_is_selected(): void
    {
        $shipped = config('site.name');
        config()->set('site.name', 'North Star Plumbing');
        config()->set('site.logo_media_id', null);
        config()->set('theme.logos', []);

        $this->get('/')
            ->assertSee('aria-label="North Star Plumbing home"', false)
            ->assertSee('>North Star Plumbing</span>', false)
            ->assertDontSee('aria-label="'.$shipped.' home"', false);
    }

    public function test_video_media_cannot_be_selected_as_the_site_logo(): void
    {
        $video = Media::query()->create([
            'path' => 'media/brand-loop.mp4',
            'original_name' => 'brand-loop.mp4',
            'mime_type' => 'video/mp4',
            'size' => 2048,
            'kind' => MediaKind::Video,
            'width' => null,
            'height' => null,
            'alt' => null,
        ]);

        Livewire::test(SiteDetailsEditor::class)
            ->set('details.logo_media_id', (string) $video->id)
            ->call('save')
            ->assertHasErrors('details.logo_media_id');

        $this->assertArrayNotHasKey('logo_media_id', SiteDetails::overrides());
    }

    public function test_blank_fields_stay_hidden_rather_than_rendering_empty(): void
    {
        // Clearing a field restores the shipped default, so the default has to
        // be blank for this to describe a genuinely empty site.
        config()->set('site.phone', '');
        config()->set('site.email', '');
        config()->set('site.address', '');

        Livewire::test(SiteDetailsEditor::class)
            ->set('details.phone', '')
            ->set('details.email', '')
            ->set('details.address', '')
            ->call('save');

        $this->get('/')
            ->assertDontSee('mailto:', false)
            ->assertDontSee('tel:', false)
            ->assertDontSee('data-contact-address', false);
    }

    public function test_clearing_a_saved_value_restores_the_shipped_default(): void
    {
        // A new site ships a blank description, which cannot be seen returning,
        // so stand in a default for config/site.php to restore.
        config()->set('site.description', 'The shipped default description.');

        Livewire::test(SiteDetailsEditor::class)
            ->set('details.description', 'A custom description.')
            ->call('save');

        $this->assertSame('A custom description.', SiteDetails::overrides()['description']);

        Livewire::test(SiteDetailsEditor::class)
            ->set('details.description', '')
            ->call('save');

        $this->assertArrayNotHasKey('description', SiteDetails::overrides());

        $this->get('/')
            ->assertSee(config('site.description'))
            ->assertDontSee('A custom description.');
    }
}
