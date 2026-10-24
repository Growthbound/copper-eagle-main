<?php

namespace App\Livewire\Admin;

use App\Models\Media;
use App\Models\Setting;
use App\Support\GoogleReviews;
use App\Support\LeadConsent;
use App\Support\SiteDetails;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.admin')]
#[Title('Site details')]
class SiteDetailsEditor extends Component
{
    /** @var array<string, string> */
    public array $details = [];

    public string $consentMode = 'disclosure';

    public string $consentText = '';

    public bool $consentRequired = false;

    public string $placeId = '';

    public string $placesApiKey = '';

    public function mount(): void
    {
        $this->details = SiteDetails::formValues();

        $this->placeId = (string) Setting::read(GoogleReviews::PLACE_ID);
        $this->placesApiKey = (string) Setting::read(GoogleReviews::API_KEY);

        $this->consentMode = LeadConsent::mode();
        $this->consentText = LeadConsent::text();
        $this->consentRequired = LeadConsent::isRequired();
    }

    public function saveConsent(): void
    {
        $this->validate([
            'consentMode' => 'required|in:'.implode(',', LeadConsent::MODES),
            'consentText' => 'required_unless:consentMode,off|nullable|string|max:600',
        ], [
            'consentText.required_unless' => 'Add the wording visitors will see.',
        ]);

        LeadConsent::save($this->consentMode, $this->consentText, $this->consentRequired);

        $this->consentText = LeadConsent::text();

        session()->flash('status', 'Consent settings saved.');
    }

    public function save(): void
    {
        $this->validate(
            rules: collect(SiteDetails::fields())
                ->mapWithKeys(fn (array $definition, string $field): array => [
                    'details.'.$field => $definition['rules'],
                ])
                ->all(),
            attributes: collect(SiteDetails::fields())
                ->mapWithKeys(fn (array $definition, string $field): array => [
                    'details.'.$field => strtolower($definition['label']),
                ])
                ->all(),
        );

        SiteDetails::save($this->details);

        session()->flash('status', 'Site details saved.');
    }

    public function saveGoogle(): void
    {
        $this->validate([
            'placeId' => 'nullable|string|max:255',
            'placesApiKey' => 'nullable|string|max:255',
        ]);

        GoogleReviews::save($this->placeId, $this->placesApiKey);

        if (blank($this->placeId) || blank($this->placesApiKey)) {
            GoogleReviews::forget();

            session()->flash('status', 'Google profile cleared.');

            return;
        }

        $result = GoogleReviews::refresh();

        if (! $result['ok']) {
            $this->addError('placeId', $result['message']);

            return;
        }

        session()->flash('status', $result['message']);
    }

    public function render(): View
    {
        return view('livewire.admin.site-details-editor', [
            'fields' => SiteDetails::fields(),
            'brandImages' => Media::query()
                ->where('kind', 'image')
                ->orderBy('original_name')
                ->get(['id', 'original_name', 'title']),
            'googleRating' => GoogleReviews::current(),
            'consentModes' => [
                'off' => 'No consent wording',
                'disclosure' => 'Show wording — submitting the form is the agreement',
                'checkbox' => 'Require a tick box the visitor must check themselves',
            ],
        ]);
    }
}
