<div>
    <h1 class="mb-2 font-heading text-3xl font-bold tracking-tight">Site details</h1>
    <p class="mb-6 text-sm text-text-muted">Your contact information as it appears on the public site. Blank fields are hidden rather than shown empty.</p>

    @if (session('status'))
        <p class="mb-6 rounded-lg bg-primary/10 px-4 py-3 text-sm font-semibold text-primary">{{ session('status') }}</p>
    @endif

    <form wire:submit="save">
        <div class="admin-card">
            @foreach ($fields as $field => $definition)
                <div @class(['mb-6' => ! $loop->last])>
                    <label class="admin-label" for="field-{{ $field }}">{{ $definition['label'] }}</label>
                    <p class="mb-2 text-sm text-text-muted">{{ $definition['help'] }}</p>

                    @if ($definition['type'] === 'text')
                        <input
                            id="field-{{ $field }}"
                            type="text"
                            class="admin-input"
                            wire:model="details.{{ $field }}"
                        >
                    @elseif ($definition['type'] === 'media')
                        <select
                            id="field-{{ $field }}"
                            class="admin-input"
                            wire:model="details.{{ $field }}"
                        >
                            <option value="">Use the text wordmark</option>
                            @foreach ($brandImages as $image)
                                <option value="{{ $image->id }}">{{ $image->title ?: $image->original_name }}</option>
                            @endforeach
                        </select>
                    @else
                        <textarea
                            id="field-{{ $field }}"
                            rows="{{ $definition['type'] === 'lines' ? 4 : 3 }}"
                            class="admin-input"
                            wire:model="details.{{ $field }}"
                        ></textarea>
                    @endif

                    @error('details.'.$field)
                        <p class="admin-error">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </div>

        <button type="submit" class="admin-button mt-6">Save details</button>
    </form>

    <form wire:submit="saveGoogle" class="admin-card mt-8">
        <h2 class="mb-1 font-heading text-xl font-bold">Google profile</h2>
        <p class="mb-5 text-sm text-text-muted">
            Shows the business's real Google rating on the site, linking to the profile so visitors can read the reviews
            themselves. The average and the full review count are displayed as Google reports them.
        </p>

        @if ($googleRating)
            <p class="mb-5 rounded-lg bg-surface px-4 py-3 text-sm">
                Currently showing
                <strong>{{ number_format($googleRating['rating'], 1) }}</strong>
                from {{ number_format($googleRating['count']) }} reviews.
            </p>
        @endif

        <div class="mb-6 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="admin-label" for="google-place-id">Place ID</label>
                <p class="mb-2 text-sm text-text-muted">Find it with Google's Place ID lookup tool.</p>
                <input id="google-place-id" type="text" class="admin-input font-mono text-xs" wire:model="placeId">
                @error('placeId') <p class="admin-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="admin-label" for="google-api-key">Places API key</label>
                <p class="mb-2 text-sm text-text-muted">A Google Cloud key with the Places API enabled.</p>
                <input id="google-api-key" type="text" class="admin-input font-mono text-xs" wire:model="placesApiKey">
                @error('placesApiKey') <p class="admin-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <p class="mb-5 rounded-lg bg-surface px-4 py-3 text-sm text-text-muted">
            Individual reviews are deliberately not shown. Google returns at most five, chosen by them, and presenting a
            filtered selection of a public review feed is what the FTC's rule on review suppression prohibits. Hand-picked
            quotes belong in the testimonial records instead, where the business has permission to publish them.
        </p>

        <button type="submit" class="admin-button">Save and refresh rating</button>
    </form>

    <form wire:submit="saveConsent" class="admin-card mt-8">
        <h2 class="mb-1 font-heading text-xl font-bold">Contact permission</h2>
        <p class="mb-5 text-sm text-text-muted">
            What visitors agree to when they submit the form. The exact wording shown is saved with each enquiry, so you
            can always show what a particular person agreed to — even after you change this text.
        </p>

        <div class="mb-6">
            <label class="admin-label" for="consent-mode">How it is asked</label>
            <select id="consent-mode" class="admin-input" wire:model.live="consentMode">
                @foreach ($consentModes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('consentMode') <p class="admin-error">{{ $message }}</p> @enderror
        </div>

        @if ($consentMode !== 'off')
            <div class="mb-6">
                <label class="admin-label" for="consent-text">Wording</label>
                <p class="mb-2 text-sm text-text-muted">
                    Name the brand, message and call types, frequency, rates, STOP and HELP instructions, and whether
                    automated technology or an artificial or AI-generated voice may be used. The public form adds the
                    Privacy Policy and Terms links beside this wording. Have the complete consent reviewed by whoever
                    advises the business on compliance.
                </p>
                <textarea id="consent-text" rows="3" class="admin-input" wire:model="consentText"></textarea>
                @error('consentText') <p class="admin-error">{{ $message }}</p> @enderror
            </div>
        @endif

        @if ($consentMode === 'checkbox')
            <label class="mb-3 flex items-start gap-3 text-sm font-semibold">
                <input type="checkbox" class="mt-0.5 size-4 rounded border-border" wire:model="consentRequired">
                The form cannot be submitted without ticking it
            </label>

            <p class="mb-6 rounded-lg bg-surface px-4 py-3 text-sm text-text-muted">
                Leaving this off is the safer default. Permission for automated marketing calls or texts generally
                cannot be made a condition of requesting a quote, so requiring the tick may reduce both your enquiries
                and your legal footing. A2P registration also expects a visitor to be able to decline messaging and
                still send the request. The box is never pre-ticked either way.
            </p>
        @endif

        <button type="submit" class="admin-button">Save consent settings</button>
    </form>
</div>
