<?php

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
