<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use App\Services\Referrals\ReferralSync;
use App\Services\Referrals\TeamDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ReferralController extends Controller
{
    public function show(Request $request, string $code, TeamDirectory $directory): Response|RedirectResponse
    {
        $code = strtolower($code);
        $referral = Referral::firstOrCreate(['code' => $code], ['referral_url' => Referral::urlForCode($code)]);
        if ($request->isMethod('GET')) {
            Referral::whereKey($referral->id)->increment('visits', 1, ['last_visited_at' => now()]);
        }
        if ($referral->registered_at) {
            return $this->redirectToForm($referral);
        }

        return response()->view('referrals.register', ['code' => $code, 'referral' => $referral, 'team' => $directory->available(), 'serviceAreas' => config('referrals.service_areas')])
            ->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function store(Request $request, string $code, TeamDirectory $directory, ReferralSync $sync): RedirectResponse
    {
        $code = strtolower($code);
        $existing = Referral::where('code', $code)->first();
        if ($existing?->registered_at) {
            return $this->redirectToForm($existing);
        }
        $team = $directory->available();
        $details = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'business_name' => ['required', 'string', 'max:255'],
            'service_areas' => ['sometimes', 'array', 'max:7'],
            'service_areas.*' => ['required', 'string', 'distinct', Rule::in(config('referrals.service_areas'))],
            'main_contact_id' => ['required', 'string', Rule::in($team->pluck('highlevel_id')->all())],
        ]);
        $details['service_areas'] = $details['service_areas'] ?? [];
        $contact = $team->firstWhere('highlevel_id', $details['main_contact_id']);
        $referral = Referral::firstOrCreate(['code' => $code], ['referral_url' => Referral::urlForCode($code)]);
        // The conditional update makes claiming existing inventory atomic, including on SQLite.
        $claimed = Referral::whereKey($referral->id)->whereNull('registered_at')->update([
            ...$details,
            'name' => $details['first_name'].' '.$details['last_name'],
            'main_contact_name' => $contact->name,
            'highlevel_location_id' => $contact->location_id,
            'registered_at' => now(),
        ]);
        $referral->refresh();
        if (! $claimed) {
            return $this->redirectToForm($referral);
        }
        // Sync failure is recorded locally and must never undo a successful registration.
        $sync->sync($referral);
        $request->session()->put('registered_referral_id', $referral->id);

        return redirect()->route('referrals.welcome', ['code' => $code]);
    }

    public function welcome(Request $request, string $code): Response
    {
        $referral = Referral::where('code', strtolower($code))->whereNotNull('registered_at')->firstOrFail();
        abort_unless($request->session()->get('registered_referral_id') === $referral->id, 404);

        return response()->view('referrals.welcome', compact('referral'))
            ->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function redirectToForm(Referral $referral): RedirectResponse
    {
        return redirect()->away($referral->destinationUrl())
            ->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
