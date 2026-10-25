@props([
    'purpose' => 'inquiry',
    'instanceId' => 'quote-form',
    'preview' => false,
    'variant' => 'card',
    'showMessage' => true,
    'submitLabel' => 'Request a visit',
])

@php
    $purpose = App\Support\WebsiteForms::purpose($purpose);
    $embed = $preview ? null : App\Support\WebsiteForms::embed($purpose, $instanceId);
@endphp

{{--
    Retained for the planned migration away from HighLevel. Activate our native
    forms by setting WEBSITE_FORM_PROVIDER=native only after native delivery and
    follow-up have been verified. While HighLevel is active, real native
    submissions are disabled. Preview mode never persists a lead.
--}}
@if ($preview || (App\Support\WebsiteForms::nativeEnabled() && $purpose === 'inquiry'))
    <livewire:quote-form
        :variant="$variant"
        :show-message="$showMessage"
        :submit-label="$submitLabel"
        :form-id="$instanceId"
        :preview="$preview"
        :key="'website-'.$instanceId"
    />
@elseif ($embed)
    <x-forms.highlevel :embed="$embed" />
@else
    <div class="website-form-alternative">
        <p>The online form is unavailable. Please call us to get in touch.</p>
        <x-call-link />
    </div>
@endif
