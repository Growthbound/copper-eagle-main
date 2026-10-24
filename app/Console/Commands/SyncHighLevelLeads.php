<?php

namespace App\Console\Commands;

use App\HighLevel\LeadSync;
use App\Models\HighLevelDelivery;
use Illuminate\Console\Command;

class SyncHighLevelLeads extends Command
{
    protected $signature = 'highlevel:sync-leads';

    protected $description = 'Retry up to ten due website lead deliveries to HighLevel';

    public function handle(LeadSync $sync): int
    {
        HighLevelDelivery::query()->where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('locked_until')->orWhere('locked_until', '<=', now()))
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('id')->limit(10)->pluck('id')->each(fn ($id) => $sync->send($id));

        $this->info('Due deliveries checked. See lead statuses in admin for results.');

        return self::SUCCESS;
    }
}
