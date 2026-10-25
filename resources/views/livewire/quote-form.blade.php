<div
    @class([
        'quote-form',
        'quote-form--split' => $variant === 'split',
    ])
    data-quote-form-variant="{{ $variant }}"
    data-preview="{{ $preview ? 'true' : 'false' }}"
>
    @if ($sent)
        <div class="quote-sent" role="status">
            <h3>Thanks — we have your request.</h3>
            <p>We'll follow up shortly to confirm the details and find a time that works.</p>
            <button type="button" class="text-link" wire:click="$set('sent', false)">Send another request</button>
        </div>
    @else
        @error('provider') <p class="quote-error" role="alert">{{ $message }}</p> @enderror
        <form wire:submit="submit" novalidate>
            <div class="quote-grid">
                <div @class(['quote-field', 'quote-field-name', 'quote-grid-full' => $variant === 'split'])>
                    <label class="quote-label" for="{{ $formId }}-name">Name</label>
                    <input id="{{ $formId }}-name" type="text" class="quote-input" wire:model="name" autocomplete="name" required @error('name') aria-invalid="true" aria-describedby="{{ $formId }}-name-error" @enderror>
                    @error('name') <p id="{{ $formId }}-name-error" class="quote-error">{{ $message }}</p> @enderror
                </div>

                <div class="quote-field quote-field-email">
                    <label class="quote-label" for="{{ $formId }}-email">Email</label>
                    <input id="{{ $formId }}-email" type="email" class="quote-input" wire:model="email" autocomplete="email" @error('email') aria-invalid="true" aria-describedby="{{ $formId }}-email-error" @enderror>
                    @error('email') <p id="{{ $formId }}-email-error" class="quote-error">{{ $message }}</p> @enderror
                </div>

                <div class="quote-field quote-field-phone">
                    <label class="quote-label" for="{{ $formId }}-phone">Phone</label>
                    <input id="{{ $formId }}-phone" type="tel" class="quote-input" wire:model="phone" autocomplete="tel" @error('phone') aria-invalid="true" aria-describedby="{{ $formId }}-phone-error" @enderror>
                    @error('phone') <p id="{{ $formId }}-phone-error" class="quote-error">{{ $message }}</p> @enderror
                </div>

                <div @class(['quote-field', 'quote-field-service', 'quote-grid-full' => $variant === 'split'])>
                    <label class="quote-label" for="{{ $formId }}-service">Service</label>
                    <select id="{{ $formId }}-service" class="quote-input" wire:model="service" @error('service') aria-invalid="true" aria-describedby="{{ $formId }}-service-error" @enderror>
                        <option value="">Not sure yet</option>
                        @foreach ($services as $service)
                            <option value="{{ $service }}">{{ $service }}</option>
                        @endforeach
                    </select>
                    @error('service') <p id="{{ $formId }}-service-error" class="quote-error">{{ $message }}</p> @enderror
                </div>

                @if ($showMessage)
                    <div class="quote-field quote-field-message quote-grid-full">
                        <label class="quote-label" for="{{ $formId }}-message">What can we help with?</label>
                        <textarea id="{{ $formId }}-message" rows="4" class="quote-input" wire:model="message" @error('message') aria-invalid="true" aria-describedby="{{ $formId }}-message-error" @enderror></textarea>
                        @error('message') <p id="{{ $formId }}-message-error" class="quote-error">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>

            <div class="quote-trap" aria-hidden="true">
                <label for="{{ $formId }}-equipment-model">Equipment model number</label>
                <input id="{{ $formId }}-equipment-model" name="equipment_model" type="text" tabindex="-1" autocomplete="off" data-lpignore="true" data-1p-ignore wire:model="equipmentModel">
                <label for="{{ $formId }}-filter-size">Product serial number</label>
                <input id="{{ $formId }}-filter-size" name="filter_size" type="text" tabindex="-1" autocomplete="off" data-lpignore="true" data-1p-ignore wire:model="filterSize">
            </div>

            @if ($consentMode === 'checkbox')
                <label class="quote-consent" for="{{ $formId }}-contact-consent">
                    <input
                        id="{{ $formId }}-contact-consent"
                        type="checkbox"
                        wire:model="consent"
                        @if ($consentRequired) required @endif
                        aria-describedby="{{ $formId }}-contact-consent-details"
                    >
                    <span>
                        {{ $consentText }}
                        See our
                        <a href="/privacy-policy/">Privacy Policy</a>
                        and
                        <a href="/terms-and-conditions/">Terms and Conditions</a>.
                    </span>
                </label>
                <p id="{{ $formId }}-contact-consent-details" class="quote-note">
                    The box starts unchecked. You can send this request without agreeing to automated calls or texts.
                </p>
                @error('consent') <p class="quote-error">{{ $message }}</p> @enderror
            @endif

            <button type="submit" class="button button-orange quote-submit">
                <span wire:loading.remove wire:target="submit">{{ $submitLabel }}</span>
                <span wire:loading wire:target="submit">Sending…</span>
            </button>

            @if ($consentMode === 'disclosure')
                <p class="quote-note">{{ $consentText }}</p>
            @endif

            <p class="quote-note">We'll only use your details to answer this request.</p>
        </form>
    @endif
</div>
