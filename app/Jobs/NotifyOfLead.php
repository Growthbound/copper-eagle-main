<?php

namespace App\Jobs;

use App\Mail\LeadReceived;
use App\Models\Lead;
use App\Support\LeadNotifications;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails the owner that an enquiry arrived.
 *
 * Runs after the response so a slow mail server never delays the visitor, and
 * swallows its own failures: the lead is already saved, and a bounced
 * notification must not surface as an error on the thank-you screen.
 */
class NotifyOfLead
{
    use Queueable;

    public function __construct(private readonly int $leadId) {}

    public function handle(): void
    {
        if (! LeadNotifications::shouldSend()) {
            return;
        }

        $lead = Lead::query()->find($this->leadId);

        if ($lead === null) {
            return;
        }

        foreach (LeadNotifications::recipients() as $recipient) {
            try {
                Mail::to($recipient)->send(new LeadReceived($lead));
            } catch (Throwable $e) {
                Log::error('Could not email the lead notification.', [
                    'lead_id' => $lead->id,
                    'recipient' => $recipient,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
