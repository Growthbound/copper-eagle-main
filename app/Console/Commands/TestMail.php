<?php

namespace App\Console\Commands;

use App\Mail\LeadReceived;
use App\Models\Lead;
use App\Support\LeadNotifications;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Proves the mail configuration from the container console.
 *
 * Available before an admin account exists and without a browser, which is
 * when mail is usually being set up. Reports the provider's own error, because
 * that is what names a wrong region endpoint, credentials that are an IAM key
 * rather than SMTP credentials, or a sender on an unverified domain.
 */
class TestMail extends Command
{
    protected $signature = 'mail:test {recipient : Where to send the test}';

    protected $description = 'Send a test notification to confirm mail actually delivers';

    public function handle(): int
    {
        $recipient = (string) $this->argument('recipient');

        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->components->error($recipient.' is not a valid email address.');

            return self::FAILURE;
        }

        $this->components->info('Sending as '.(config('mail.from.address') ?: 'nobody — MAIL_FROM_ADDRESS is not set')
            .' via '.config('mail.default').'.');

        if ($problem = LeadNotifications::problem()) {
            $this->components->warn($problem);
        }

        try {
            Mail::to($recipient)->send(new LeadReceived($this->exampleLead()));
        } catch (Throwable $e) {
            $this->components->error('The mail server rejected it: '.$e->getMessage());

            return self::FAILURE;
        }

        if (LeadNotifications::isPretending()) {
            $this->components->warn('Written to the application log. Nothing was actually delivered.');

            return self::SUCCESS;
        }

        $this->components->info('Accepted for delivery to '.$recipient.'.');
        $this->components->warn('Accepted is not the same as arrived. Check the inbox, and the spam folder.');

        return self::SUCCESS;
    }

    private function exampleLead(): Lead
    {
        $lead = new Lead([
            'name' => 'Test Submission',
            'email' => 'test@example.com',
            'phone' => '555 000 0000',
            'service' => 'Test service',
            'message' => 'Sent by php artisan mail:test to confirm delivery.',
        ]);

        $lead->id = 0;
        $lead->created_at = now();

        return $lead;
    }
}
