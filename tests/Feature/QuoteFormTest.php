<?php

namespace Tests\Feature;

use App\Livewire\QuoteForm;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\Concerns\IsolatesManagedContent;
use Tests\TestCase;

class QuoteFormTest extends TestCase
{
    use IsolatesManagedContent;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('quote-form:127.0.0.1');
    }

    public function test_a_visitor_request_is_stored_as_a_new_lead(): void
    {
        Livewire::test(QuoteForm::class)
            ->set('firstName', 'Taylor')
            ->set('lastName', 'Example')
            ->set('email', 'taylor@example.test')
            ->set('phone', '555 555 0100')
            ->set('service', 'Example Service')
            ->set('message', 'Please call before visiting.')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('sent', true);

        $lead = Lead::query()->sole();

        $this->assertSame('Taylor', $lead->first_name);
        $this->assertSame('Example', $lead->last_name);
        // Everything that reads one full name keeps working.
        $this->assertSame('Taylor Example', $lead->name);
        $this->assertSame('taylor@example.test', $lead->email);
        $this->assertSame('Example Service', $lead->service);
        $this->assertSame('new', $lead->status);
    }

    public function test_a_request_without_a_phone_number_is_rejected(): void
    {
        // The company calls or texts back, so an email alone is not enough.
        Livewire::test(QuoteForm::class)
            ->set('firstName', 'Taylor')
            ->set('lastName', 'Example')
            ->set('email', 'taylor@example.test')
            ->call('submit')
            ->assertHasErrors(['phone' => 'required'])
            ->assertHasNoErrors('email');

        $this->assertSame(0, Lead::query()->count());
    }

    public function test_email_is_optional_but_must_be_valid_when_given(): void
    {
        Livewire::test(QuoteForm::class)
            ->set('firstName', 'Taylor')
            ->set('lastName', 'Example')
            ->set('phone', '555 555 0100')
            ->set('email', 'not-an-email')
            ->call('submit')
            ->assertHasErrors(['email' => 'email']);

        $this->assertSame(0, Lead::query()->count());
    }

    public function test_the_phone_field_is_marked_required_and_email_optional(): void
    {
        $html = Livewire::test(QuoteForm::class, ['formId' => 'contact-form'])->html();
        $xpath = $this->xpath($html);

        $phone = $xpath->query('//input[@id="contact-form-phone"]')->item(0);
        $email = $xpath->query('//input[@id="contact-form-email"]')->item(0);

        $this->assertTrue($phone->hasAttribute('required'));
        $this->assertFalse($email->hasAttribute('required'));
        $this->assertStringContainsString('(optional)', $xpath->query('//label[@for="contact-form-email"]')->item(0)->textContent);
    }

    public function test_a_phone_number_alone_is_enough_to_submit(): void
    {
        Livewire::test(QuoteForm::class)
            ->set('firstName', 'Taylor')
            ->set('lastName', 'Example')
            ->set('phone', '555 555 0100')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame(1, Lead::query()->count());
    }

    public function test_a_filled_honeypot_is_silently_discarded(): void
    {
        Livewire::test(QuoteForm::class)
            ->set('firstName', 'Spam')
            ->set('lastName', 'Bot')
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
                ->set('firstName', 'Taylor')
                ->set('lastName', 'Example')
                ->set('phone', '555 555 0100')
                ->call('submit')
                ->assertHasNoErrors();
        }

        Livewire::test(QuoteForm::class)
            ->set('firstName', 'Taylor')
            ->set('lastName', 'Example')
            ->set('phone', '555 555 0100')
            ->call('submit')
            ->assertHasErrors('firstName');

        $this->assertSame(5, Lead::query()->count());
    }

    public function test_first_and_last_name_are_separate_labelled_fields(): void
    {
        $html = Livewire::test(QuoteForm::class, ['formId' => 'contact-form'])->html();
        $xpath = $this->xpath($html);

        foreach ([
            'contact-form-first-name' => ['First name', 'given-name', 'firstName'],
            'contact-form-last-name' => ['Last name', 'family-name', 'lastName'],
        ] as $id => [$label, $autocomplete, $model]) {
            $input = $xpath->query('//input[@id="'.$id.'"]')->item(0);
            $this->assertNotNull($input, "No {$label} input.");
            $this->assertSame($model, $input->getAttribute('wire:model'));
            $this->assertSame($autocomplete, $input->getAttribute('autocomplete'));
            $this->assertTrue($input->hasAttribute('required'), "{$label} is required on the live form.");

            $labels = $xpath->query('//label[@for="'.$id.'"]');
            $this->assertCount(1, $labels);
            $this->assertSame($label, trim($labels->item(0)->textContent));
        }

        $this->assertStringNotContainsString('wire:model="name"', $html);
        $this->assertStringNotContainsString('id="contact-form-name"', $html);
        $this->assertLessThan(
            strpos($html, 'id="contact-form-last-name"'),
            strpos($html, 'id="contact-form-first-name"'),
            'First name comes before last name.',
        );
    }

    public function test_first_and_last_name_are_both_required(): void
    {
        Livewire::test(QuoteForm::class)
            ->set('email', 'taylor@example.test')
            ->call('submit')
            ->assertHasErrors(['firstName' => 'required', 'lastName' => 'required']);

        Livewire::test(QuoteForm::class)
            ->set('firstName', 'Taylor')
            ->set('lastName', '   ')
            ->set('email', 'taylor@example.test')
            ->call('submit')
            ->assertHasErrors(['lastName' => 'required'])
            ->assertHasNoErrors('firstName');

        $this->assertSame(0, Lead::query()->count());
    }

    public function test_name_errors_are_tied_to_their_own_fields(): void
    {
        $html = Livewire::test(QuoteForm::class, ['formId' => 'contact-form'])
            ->set('email', 'taylor@example.test')
            ->call('submit')
            ->html();
        $xpath = $this->xpath($html);

        foreach (['first-name', 'last-name'] as $field) {
            $input = $xpath->query('//input[@id="contact-form-'.$field.'"]')->item(0);
            $this->assertSame('true', $input->getAttribute('aria-invalid'));
            $this->assertSame('contact-form-'.$field.'-error', $input->getAttribute('aria-describedby'));
            $this->assertCount(1, $xpath->query('//p[@id="contact-form-'.$field.'-error"]'));
        }
    }

    public function test_names_are_trimmed_before_they_are_stored(): void
    {
        Livewire::test(QuoteForm::class)
            ->set('firstName', '  Taylor ')
            ->set('lastName', ' Example  ')
            ->set('phone', '555 555 0100')
            ->call('submit')
            ->assertHasNoErrors();

        $lead = Lead::query()->sole();

        $this->assertSame('Taylor', $lead->first_name);
        $this->assertSame('Example', $lead->last_name);
        $this->assertSame('Taylor Example', $lead->name);
    }

    public function test_a_tab_opened_before_the_split_still_sends_its_single_name(): void
    {
        // An old page still binds the retired single "name" input.
        Livewire::test(QuoteForm::class)
            ->set('name', 'Taylor  Van Example')
            ->set('phone', '555 555 0100')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('sent', true)
            ->assertSet('name', '');

        $lead = Lead::query()->sole();

        $this->assertSame('Taylor', $lead->first_name);
        $this->assertSame('Van Example', $lead->last_name);
        $this->assertSame('Taylor Van Example', $lead->name);
    }

    public function test_the_homepage_renders_the_request_form(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee($this->quoteFormHeading())
            ->assertSee('Request a visit');
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);

        return new \DOMXPath($dom);
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
