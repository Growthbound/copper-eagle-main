<x-layouts.referral title="Your referral link is ready">
    <p class="mb-3 text-sm font-semibold uppercase tracking-widest text-text-muted">You're registered</p>
    <h1 class="mb-5 text-4xl font-bold leading-tight">Your referral link is ready</h1>
    <p class="mb-6 text-text-muted">Welcome, {{ $referral->first_name ?: $referral->name }}. This card now belongs to you. Every time someone scans it, they'll go straight to our homeowner referral form.</p>
    <div class="admin-card">
        <label for="referral-link" class="admin-label">Save and share your personal link</label>
        <input id="referral-link" class="admin-input" type="text" readonly value="{{ $referral->publicUrl() }}" onfocus="this.select()">
        <p class="mt-3 text-sm text-text-muted">Copy this link into a text, bookmark it on your phone, or keep sharing your printed QR card.</p>
        <a href="{{ $referral->publicUrl() }}" class="admin-button mt-6 w-full">Open homeowner referral form</a>
    </div>
    <p class="mt-6 text-sm text-text-muted">Your {{ config('site.name') }} main contact: <strong>{{ $referral->main_contact_name }}</strong></p>
</x-layouts.referral>
