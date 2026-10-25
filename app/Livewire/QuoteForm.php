<?php

namespace App\Livewire;

use App\Content\ServiceRepository;
use App\Models\Lead;
use App\Support\LeadConsent;
use App\Support\WebsiteForms;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class QuoteForm extends Component
{
    #[Validate('required|string|max:120')]
    public string $firstName = '';

    #[Validate('required|string|max:120')]
    public string $lastName = '';

    #[Validate('nullable|email|max:190')]
    public string $email = '';

    // The company replies by calling or texting, so a phone number is the one
    // contact detail every request needs. Email is optional.
    #[Validate('required|string|max:40', message: ['required' => 'Add a phone number so we can call or text you back.'])]
    public string $phone = '';

    #[Validate('nullable|string|max:120')]
    public string $service = '';

    #[Validate('nullable|string|max:2000')]
    public string $message = '';

    /**
     * Hidden from real visitors with CSS; bots that fill every field land here.
     */
    public string $equipmentModel = '';

    public string $filterSize = '';

    // Accept old Livewire snapshots without letting contact-profile autofill
    // discard a real enquiry. These legacy fields are no longer traps.
    public string $website = '';

    public string $fax = '';

    /**
     * The single "name" input a page loaded before the first/last split still
     * binds. Only used to fill both names when they are empty.
     */
    public string $name = '';

    /**
     * Starts unticked, always. A pre-ticked box is not an affirmative act.
     */
    public bool $consent = false;

    /**
     * One per separately asked text-message permission (LeadConsent::CHOICE_KINDS).
     * Each starts unticked and stays optional.
     */
    public bool $consentService = false;

    public bool $consentMarketing = false;

    public bool $sent = false;

    public string $variant = 'card';

    public bool $showMessage = true;

    public string $submitLabel = 'Request a visit';

    #[Locked]
    public bool $preview = false;

    #[Locked]
    public string $formId = 'quote-form';

    public function mount(
        string $variant = 'card',
        bool $showMessage = true,
        string $submitLabel = 'Request a visit',
        bool $preview = false,
        string $formId = 'quote-form',
    ): void {
        $this->variant = in_array($variant, ['card', 'split'], true) ? $variant : 'card';
        $this->showMessage = $showMessage;
        $this->submitLabel = trim($submitLabel) !== '' ? trim($submitLabel) : 'Request a visit';
        $this->preview = $preview;
        $this->formId = preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $formId) === 1
            ? $formId
            : 'quote-form';
    }

    public function submit(): void
    {
        // Retained for the planned migration away from HighLevel. Activate our
        // native forms with WEBSITE_FORM_PROVIDER=native after native delivery
        // and follow-up have been verified. Recheck on every submission so old
        // browser tabs cannot keep accepting leads after the provider switch.
        if (! $this->preview && ! WebsiteForms::nativeEnabled()) {
            throw ValidationException::withMessages([
                'provider' => 'Please reload this page to use the current form. Your request has not been sent.',
            ]);
        }

        // Check raw values before validation or any lead side effects. Even
        // whitespace or "0" means a field invisible to visitors was filled.
        if ($this->equipmentModel !== '' || $this->filterSize !== '') {
            $this->showConfirmation();

            return;
        }

        $this->adoptLegacyName();
        $this->firstName = trim($this->firstName);
        $this->lastName = trim($this->lastName);

        $this->validate();

        if (LeadConsent::isRequired() && ! $this->consent) {
            throw ValidationException::withMessages([
                'consent' => 'Please tick the box so we know how we may contact you.',
            ]);
        }

        if ($this->preview) {
            $this->showConfirmation();

            return;
        }

        $key = 'quote-form:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 5)) {
            throw ValidationException::withMessages([
                'firstName' => 'You have sent several requests already. Please call us instead.',
            ]);
        }

        RateLimiter::hit($key, decaySeconds: 3600);

        Lead::query()->create([
            // Kept whole for the inbox, email, webhooks and CRM name fields.
            'name' => $this->firstName.' '.$this->lastName,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email ?: null,
            'phone' => $this->phone ?: null,
            'service' => $this->service ?: null,
            'message' => $this->message ?: null,
            ...LeadConsent::record($this->consent, request()->ip(), [
                'service' => $this->consentService,
                'marketing' => $this->consentMarketing,
            ]),
        ]);

        $this->showConfirmation();
    }

    private function adoptLegacyName(): void
    {
        $legacy = trim($this->name);

        if ($legacy === '' || trim($this->firstName) !== '' || trim($this->lastName) !== '') {
            return;
        }

        $parts = preg_split('/\s+/u', $legacy, 2) ?: [$legacy];
        $this->firstName = $parts[0];
        $this->lastName = $parts[1] ?? '';
    }

    private function showConfirmation(): void
    {
        $this->reset([
            'firstName', 'lastName', 'name', 'email', 'phone', 'service', 'message',
            'equipmentModel', 'filterSize', 'website', 'fax',
            'consent', 'consentService', 'consentMarketing',
        ]);
        $this->resetValidation();
        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.quote-form', [
            'services' => app(ServiceRepository::class)->names(),
            'consentMode' => LeadConsent::mode(),
            'consentText' => LeadConsent::text(),
            'consentRequired' => LeadConsent::isRequired(),
            'consentChoices' => LeadConsent::hasChoices() ? LeadConsent::choices() : [],
            'consentChoicesTitle' => LeadConsent::choicesTitle(),
            'consentChoicesNote' => LeadConsent::choicesNote(),
            'consentChoicesLinks' => LeadConsent::choicesLinks(),
        ]);
    }
}
