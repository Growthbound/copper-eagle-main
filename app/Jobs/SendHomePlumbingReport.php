<?php

namespace App\Jobs;

use App\Mail\HomePlumbingReportMail;
use App\Models\Lead;
use App\Support\LeadNotifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendHomePlumbingReport
{
    use Queueable;

    private const MAX_ATTEMPTS = 3;

    public function __construct(private readonly int $leadId) {}

    /** @return Builder<Lead> */
    public static function due(): Builder
    {
        return Lead::query()->whereNotNull('quiz_answers')->whereNotNull('email')
            ->whereNull('quiz_report_sent_at')->where('quiz_report_attempts', '<', self::MAX_ATTEMPTS)
            ->where(fn (Builder $query) => $query->whereNull('quiz_report_attempted_at')
                ->orWhere('quiz_report_attempted_at', '<=', now()->subMinutes(5)));
    }

    public function handle(): void
    {
        if (LeadNotifications::isPretending()) {
            return;
        }

        // Reserve the attempt in the database shared by the web and scheduler containers.
        $reserved = self::due()->whereKey($this->leadId)->update([
            'quiz_report_attempted_at' => now(),
            'quiz_report_attempts' => DB::raw('quiz_report_attempts + 1'),
        ]);
        if (! $reserved) {
            return;
        }

        $lead = Lead::query()->find($this->leadId);
        if ($lead === null) {
            return;
        }

        try {
            $sent = Mail::to($lead->email)->send(new HomePlumbingReportMail($lead->first_name, $lead->quiz_answers));
        } catch (Throwable $exception) {
            Log::warning('Could not email the home plumbing report.', [
                'lead_id' => $lead->id,
                'error_type' => $exception::class,
            ]);

            return;
        }

        if ($sent !== null) {
            $lead->forceFill(['quiz_report_sent_at' => now()])->saveQuietly();
        }
    }
}
