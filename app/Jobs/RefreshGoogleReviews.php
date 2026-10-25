<?php

namespace App\Jobs;

use App\Support\GoogleReviews;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Refreshes the stored Google rating after the response has been sent, so a
 * visitor never waits on Google. Failures are ignored: the previously stored
 * rating keeps displaying until the next attempt succeeds.
 */
class RefreshGoogleReviews
{
    use Queueable;

    public function handle(): void
    {
        if (GoogleReviews::isConfigured()) {
            GoogleReviews::refresh();
        }
    }
}
