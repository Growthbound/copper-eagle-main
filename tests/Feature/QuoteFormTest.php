<?php

namespace Tests\Feature;

use App\Livewire\QuoteForm;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class QuoteFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('quote-form:127.0.0.1');
    }

    public function test_a_visitor_request_is_stored_as_a_new_lead(): void
    {
        Livewire::test(QuoteForm::class)
            ->set('name', 'Taylor Example')
            ->set('email', 'taylor@example.test')
            ->set('service', 'Example Service')
            ->set('message', 'Please call before visiting.')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('sent', true);

        $lead = Lead::query()->sole();

        $this->assertSame('Taylor Example', $lead->name);
        $this->assertSame('taylor@example.test', $lead->email);
        $this->assertSame('Example Service', $lead->service);
        $this->assertSame('new', $lead->status);
    }

    public function test_a_request_without_any_way_to_reply_is_rejected(): void
    {
        Livewire::test(QuoteForm::class)
            ->set('name', 'Taylor Example')
            ->call('submit')
            ->assertHasErrors('email');

        $this->assertSame(0, Lead::query()->count());
    }

    public function test_a_phone_number_alone_is_enough_to_submit(): void
    {
        Livewire::test(QuoteForm::class)
            ->set('name', 'Taylor Example')
            ->set('phone', '555 555 0100')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame(1, Lead::query()->count());
    }

    public function test_a_filled_honeypot_is_silently_discarded(): void
    {
        Livewire::test(QuoteForm::class)
            ->set('name', 'Spam Bot')
            ->set('email', 'bot@example.test')
            ->set('equipmentModel', 'Model ABC123')
            ->call('submit')
            ->assertSet('sent', true);

        $this->assertSame(0, Lead::query()->count());
    }

    public function test_the_form_stops_accepting_after_repeated_submissions(): void
    {
        for ($submission = 0; $submission < 5; $submission++) {
            Livewire::test(QuoteForm::class)
                ->set('name', 'Taylor Example')
                ->set('phone', '555 555 0100')
                ->call('submit')
                ->assertHasNoErrors();
        }

        Livewire::test(QuoteForm::class)
            ->set('name', 'Taylor Example')
            ->set('phone', '555 555 0100')
            ->call('submit')
            ->assertHasErrors('name');

        $this->assertSame(5, Lead::query()->count());
    }

    public function test_the_homepage_renders_the_request_form(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee($this->quoteFormHeading())
            ->assertSee('Request a visit');
    }

    /**
     * Read from the page file: this test is about the form rendering, not
     * about the words above it.
     */
    private function quoteFormHeading(): string
    {
        $page = json_decode(
            (string) file_get_contents(base_path('content/pages/home.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        foreach ($page['blocks'] as $block) {
            if (($block['type'] ?? null) === 'cta/quote-form') {
                return $block['props']['heading'];
            }
        }

        $this->fail('The homepage has no quote form block.');
    }
}
