<?php

namespace Tests\Feature;

use App\Livewire\QuoteForm;
use App\Models\HighLevelConnection;
use App\Models\Webhook;
use App\Support\LeadConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuoteFormSpamProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['website-forms.provider' => 'native']);
        RateLimiter::clear('quote-form:127.0.0.1');
        Http::preventStrayRequests();
        Bus::fake();

        HighLevelConnection::query()->create([
            'id' => 1,
            'location_id' => 'exampleLocation',
            'location_name' => 'Example Service',
            'token' => 'test-token',
            'enabled' => true,
        ]);
        Webhook::query()->create(['name' => 'Example CRM', 'url' => 'https://crm.example.test/hook', 'is_active' => true]);
    }

    #[DataProvider('filledTraps')]
    public function test_either_filled_trap_discards_spam_before_any_delivery(string $field, string $value): void
    {
        foreach (['card', 'split'] as $variant) {
            Livewire::test(QuoteForm::class, ['variant' => $variant])
                ->set('firstName', 'Sales')
                ->set('lastName', 'Bot')
                ->set('email', 'bot@example.test')
                ->set('message', 'Reply YES for our virtual assistant offer.')
                ->set($field, $value)
                ->call('submit')
                ->assertHasNoErrors()
                ->assertSet('sent', true);
        }

        $this->assertDatabaseCount('leads', 0);
        $this->assertDatabaseCount('high_level_deliveries', 0);
        $this->assertDatabaseCount('webhook_deliveries', 0);
        Bus::assertNothingDispatched();
        Http::assertNothingSent();
    }

    public static function filledTraps(): array
    {
        return [
            'equipment model' => ['equipmentModel', 'Model ABC123'],
            'equipment model whitespace' => ['equipmentModel', ' '],
            'equipment model zero' => ['equipmentModel', '0'],
            'filter size' => ['filterSize', 'bot-filled'],
            'filter size whitespace' => ['filterSize', ' '],
            'filter size zero' => ['filterSize', '0'],
        ];
    }

    public function test_a_bot_is_discarded_before_validation_and_required_consent(): void
    {
        LeadConsent::save('checkbox', 'I agree to be contacted.', required: true);

        Livewire::test(QuoteForm::class)
            ->set('equipmentModel', 'Model ABC123')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('sent', true);

        $this->assertDatabaseCount('leads', 0);
        Bus::assertNothingDispatched();
    }

    public function test_discarded_spam_does_not_use_up_a_visitors_submission_allowance(): void
    {
        for ($submission = 0; $submission < 6; $submission++) {
            Livewire::test(QuoteForm::class)
                ->set('firstName', 'Sales')
                ->set('lastName', 'Bot')
                ->set('phone', '5555550199')
                ->set('filterSize', 'bot-filled')
                ->call('submit')
                ->assertHasNoErrors()
                ->assertSet('sent', true);
        }

        Livewire::test(QuoteForm::class)
            ->set('firstName', 'Taylor')
            ->set('lastName', 'Example')
            ->set('phone', '5555550100')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('sent', true);

        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseCount('high_level_deliveries', 1);
    }

    public function test_discard_clears_earlier_errors_and_the_traps_for_another_request(): void
    {
        $form = Livewire::test(QuoteForm::class)->call('submit')->assertHasErrors('firstName');

        $form->set('filterSize', 'bot-filled')->call('submit')
            ->assertHasNoErrors()->assertSet('sent', true)->assertSet('filterSize', '');
        $this->assertDatabaseCount('leads', 0);

        $form->set('sent', false)->set('firstName', 'Taylor')->set('lastName', 'Example')->set('email', 'taylor@example.test')->set('phone', '555 555 0100')
            ->call('submit')->assertHasNoErrors()->assertSet('sent', true);
        $this->assertDatabaseCount('leads', 1);
    }

    public function test_the_traps_are_named_but_excluded_from_keyboard_and_screen_reader_navigation(): void
    {
        foreach (['card', 'split'] as $variant) {
            $html = Livewire::test(QuoteForm::class, ['variant' => $variant, 'formId' => 'inquiry-'.$variant])->html();
            $dom = new \DOMDocument;
            @$dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);

            foreach (['equipment_model', 'filter_size'] as $field) {
                $inputs = $xpath->query('//div[@aria-hidden="true"]//input[@name="'.$field.'"]');
                $this->assertCount(1, $inputs);
                $input = $inputs->item(0);
                $this->assertSame('inquiry-'.$variant.'-'.str_replace('_', '-', $field), $input->getAttribute('id'));
                $this->assertSame('-1', $input->getAttribute('tabindex'));
                $this->assertSame('off', $input->getAttribute('autocomplete'));

                // Named like a real field so a bot fills it in.
                $labels = $xpath->query('//label[@for="'.$input->getAttribute('id').'"]');
                $this->assertCount(1, $labels);
                $this->assertNotSame('', trim($labels->item(0)->textContent));
            }

            $this->assertStringNotContainsString('name="website"', $html);
            $this->assertStringNotContainsString('name="fax"', $html);
            foreach (['given-name', 'family-name', 'email', 'tel'] as $autocomplete) {
                $this->assertStringContainsString('autocomplete="'.$autocomplete.'"', $html);
            }
        }
    }

    public function test_autofilled_legacy_fields_in_an_old_tab_cannot_discard_a_real_enquiry(): void
    {
        Livewire::test(QuoteForm::class)
            ->set('firstName', 'Taylor')
            ->set('lastName', 'Example')
            ->set('email', 'taylor@example.test')
            ->set('phone', '555 555 0100')
            ->set('website', 'https://example.test')
            ->set('fax', '5555550199')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('sent', true)
            ->assertSet('website', '')
            ->assertSet('fax', '');

        $this->assertDatabaseHas('leads', ['email' => 'taylor@example.test']);
        $this->assertDatabaseCount('high_level_deliveries', 1);
    }
}
