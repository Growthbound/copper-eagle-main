<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\AnnouncementEditor;
use App\Models\Setting;
use App\Models\User;
use App\Support\Announcement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_an_enabled_announcement_shows_on_the_public_site(): void
    {
        Livewire::test(AnnouncementEditor::class)
            ->set('enabled', true)
            ->set('message', 'Spring special, $50 off through April.')
            ->set('url', 'https://example.test/offer')
            ->set('linkLabel', 'See the offer')
            ->call('save')
            ->assertHasNoErrors();

        $this->get('/')
            ->assertSee('Spring special, $50 off through April.')
            ->assertSee('See the offer');
    }

    public function test_nothing_renders_while_the_announcement_is_switched_off(): void
    {
        Livewire::test(AnnouncementEditor::class)
            ->set('enabled', false)
            ->set('message', 'Hidden for now.')
            ->call('save');

        $this->get('/')->assertDontSee('Hidden for now.');
    }

    public function test_the_announcement_waits_for_its_start_time(): void
    {
        Livewire::test(AnnouncementEditor::class)
            ->set('enabled', true)
            ->set('message', 'Holiday hours begin Monday.')
            ->set('startsAt', now()->addDay()->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasNoErrors();

        $this->get('/')->assertDontSee('Holiday hours begin Monday.');

        Carbon::setTestNow(now()->addDays(2));

        $this->get('/')->assertSee('Holiday hours begin Monday.');
    }

    public function test_the_announcement_retires_itself_after_its_end_time(): void
    {
        Livewire::test(AnnouncementEditor::class)
            ->set('enabled', true)
            ->set('message', 'Winter promo ends soon.')
            ->set('endsAt', now()->addDay()->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasNoErrors();

        $this->get('/')->assertSee('Winter promo ends soon.');

        Carbon::setTestNow(now()->addDays(2));

        $this->get('/')->assertDontSee('Winter promo ends soon.');
    }

    public function test_an_end_time_before_the_start_is_rejected(): void
    {
        Livewire::test(AnnouncementEditor::class)
            ->set('enabled', true)
            ->set('message', 'Backwards window.')
            ->set('startsAt', now()->addDays(3)->format('Y-m-d\TH:i'))
            ->set('endsAt', now()->addDay()->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasErrors('endsAt');
    }

    public function test_enabling_without_a_message_is_rejected(): void
    {
        Livewire::test(AnnouncementEditor::class)
            ->set('enabled', true)
            ->set('message', '')
            ->call('save')
            ->assertHasErrors('message');
    }

    public function test_the_style_list_comes_from_the_announcement_block_folder(): void
    {
        $this->assertContains('bar', Announcement::variants());
    }

    public function test_a_missing_template_falls_back_instead_of_breaking_the_page(): void
    {
        Setting::write([
            Announcement::PREFIX.'enabled' => '1',
            Announcement::PREFIX.'message' => 'Still visible.',
            Announcement::PREFIX.'variant' => 'a-template-that-was-deleted',
        ]);

        $this->assertSame('bar', Announcement::current()['variant']);

        $this->get('/')->assertOk()->assertSee('Still visible.');
    }
}
