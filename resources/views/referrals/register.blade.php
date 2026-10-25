<x-layouts.referral title="Join the affiliate program">
    <p class="mb-3 text-sm font-semibold uppercase tracking-widest text-text-muted">Affiliate program</p>
    <h1 class="mb-5 text-4xl font-bold leading-tight">Welcome to the {{ config('site.name') }} Affiliate Program</h1>
    <p class="mb-6 text-text-muted">Connect your customers with us. Register your card once, then share your personal referral link whenever someone needs us.</p>
    <div class="mb-6 rounded-xl border border-border bg-surface p-4">
        <p class="text-sm font-semibold">Your card code</p>
        <p class="break-all font-mono text-lg">{{ $code }}</p>
    </div>
    @if ($team->isEmpty())
        <div class="admin-card" role="status">
            <h2 class="text-xl font-bold">Registration is temporarily unavailable</h2>
            <p class="mt-2 text-text-muted">Please contact your {{ config('site.name') }} representative and try again shortly. Your card has not been registered.</p>
        </div>
    @else
        <form method="POST" action="{{ route('referrals.store', ['code' => $code]) }}" class="admin-card">
            @csrf
            <div class="grid gap-5 sm:grid-cols-2">
                @foreach (['first_name' => 'First name', 'last_name' => 'Last name'] as $field => $label)
                    <div>
                        <label for="{{ $field }}" class="admin-label">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="text" class="admin-input"
                            value="{{ is_string(old($field)) ? old($field) : '' }}" maxlength="120" required
                            autocomplete="{{ $field === 'first_name' ? 'given-name' : 'family-name' }}"
                            @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>
                        @error($field)<p id="{{ $field }}-error" class="admin-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="phone" class="admin-label">Phone</label>
                    <input id="phone" name="phone" type="tel" class="admin-input" value="{{ is_string(old('phone')) ? old('phone') : '' }}"
                        autocomplete="tel" maxlength="30" required
                        @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
                    @error('phone')<p id="phone-error" class="admin-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="email" class="admin-label">Email</label>
                    <input id="email" name="email" type="email" class="admin-input" value="{{ is_string(old('email')) ? old('email') : '' }}"
                        autocomplete="email" maxlength="255" required
                        @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                    @error('email')<p id="email-error" class="admin-error" role="alert">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="mt-5">
                <label for="business_name" class="admin-label">Business name</label>
                <input id="business_name" name="business_name" type="text" class="admin-input" value="{{ is_string(old('business_name')) ? old('business_name') : '' }}"
                    autocomplete="organization" maxlength="255" required
                    @error('business_name') aria-invalid="true" aria-describedby="business-error" @enderror>
                @error('business_name')<p id="business-error" class="admin-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <fieldset class="mt-5" @error('service_areas') aria-invalid="true" aria-describedby="service-areas-error" @enderror>
                <legend class="admin-label">Service areas</legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($serviceAreas as $area)
                        <label class="flex items-center gap-3 text-sm">
                            <input type="checkbox" name="service_areas[]" value="{{ $area }}" class="accent-current"
                                @checked(in_array($area, is_array(old('service_areas')) ? old('service_areas') : [], true))>
                            <span>{{ $area }}</span>
                        </label>
                    @endforeach
                </div>
                @error('service_areas')<p id="service-areas-error" class="admin-error" role="alert">{{ $message }}</p>@enderror
                @error('service_areas.*')<p class="admin-error" role="alert">{{ $message }}</p>@enderror
            </fieldset>
            <div class="mt-5">
                <label for="main_contact_id" class="admin-label">{{ config('site.name') }} main contact</label>
                <select id="main_contact_id" name="main_contact_id" class="admin-input" required
                    @error('main_contact_id') aria-invalid="true" aria-describedby="contact-error" @enderror>
                    <option value="">Choose your {{ config('site.name') }} contact</option>
                    @foreach ($team as $member)
                        <option value="{{ $member->highlevel_id }}" @selected(old('main_contact_id') === $member->highlevel_id)>{{ $member->name }}</option>
                    @endforeach
                </select>
                @error('main_contact_id')<p id="contact-error" class="admin-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <p class="my-5 text-sm text-text-muted">Your name and business will accompany referrals made through your link so we know who sent them.</p>
            <button type="submit" class="admin-button w-full">Register my referral link</button>
        </form>
    @endif
</x-layouts.referral>
