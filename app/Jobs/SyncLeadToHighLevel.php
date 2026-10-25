<?php

namespace App\Jobs;

use App\HighLevel\LeadSync;
use Illuminate\Foundation\Queue\Queueable;

class SyncLeadToHighLevel
{
    use Queueable;

    public function __construct(public int $deliveryId) {}

    public function handle(LeadSync $sync): void
    {
        $sync->send($this->deliveryId);
    }
}
