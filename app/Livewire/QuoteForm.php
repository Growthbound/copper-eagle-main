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
    public string $name = '';

    #[Validate('nullable|email|max:190')]
    public string $email = '';

    #[Validate('nullable|string|max:40')]
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
     * Starts unticked, always. A pre-ticked box is not an affirmative act.
     */
    public bool $consent = false;

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

        $this->validate();

        if (LeadConsent::isRequired() && ! $this->consent) {
            throw ValidationException::withMessages([
                'consent' => 'Please tick the box so we know how we may contact you.',
            ]);
        }

        if ($this->email === '' && $this->phone === '') {
            throw ValidationException::withMessages([
                'email' => 'Add an email address or a phone number so we can reply.',
            ]);
        }

        if ($this->preview) {
            $this->showConfirmation();

            return;
        }

        $key = 'quote-form:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 5)) {
            throw ValidationException::withMessages([
                'name' => 'You have sent several requests already. Please call us instead.',
            ]);
        }

        RateLimiter::hit($key, decaySeconds: 3600);

        Lead::query()->create([
            'name' => $this->name,
            'email' => $this->email ?: null,
            'phone' => $this->phone ?: null,
            'service' => $this->service ?: null,
            'message' => $this->message ?: null,
            ...LeadConsent::record($this->consent, request()->ip()),
        ]);

        $this->showConfirmation();
    }

    private function showConfirmation(): void
    {
        $this->reset(['name', 'email', 'phone', 'service', 'message', 'equipmentModel', 'filterSize', 'website', 'fax', 'consent']);
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
        ]);
    }
}
