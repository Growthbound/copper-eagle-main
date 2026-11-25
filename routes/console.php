<?php

use App\Jobs\SendHomePlumbingReport;
use App\Support\LeadNotifications;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('content:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('content:prune-expired')
    ->dailyAt('02:15')
    ->withoutOverlapping();

Schedule::command('highlevel:sync-leads')->everyMinute()->withoutOverlapping();

Artisan::command('quiz:send-reports', function () {
    if (! LeadNotifications::isPretending()) {
        foreach (SendHomePlumbingReport::due()->orderBy('id')->limit(20)->pluck('id') as $leadId) {
            SendHomePlumbingReport::dispatchSync($leadId);
        }
    }
})->purpose('Send pending home plumbing reports');

Schedule::command('quiz:send-reports')->everyMinute()->withoutOverlapping();
