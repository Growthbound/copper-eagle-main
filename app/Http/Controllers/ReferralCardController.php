<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use App\Services\Referrals\ReferralCards;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReferralCardController extends Controller
{
    public function show(Request $request, Referral $referral, ReferralCards $cards): Response
    {
        return response($cards->png($referral), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => ($request->boolean('inline') ? 'inline' : 'attachment').'; filename="'.$referral->code.'.png"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function batch(string $batch, ReferralCards $cards): Response
    {
        return response($cards->zip($batch), 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="referral-cards-'.$batch.'.zip"',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
