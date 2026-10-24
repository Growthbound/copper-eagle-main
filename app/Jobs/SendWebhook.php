<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Delivers one event to one destination.
 *
 * Retries happen inside the job rather than through the queue, because this
 * deployment runs without a worker process: jobs are dispatched after the
 * response so the visitor never waits on a third-party endpoint, and an
 * after-response job gets no second run from the queue if it fails.
 */
class SendWebhook
{
    use Queueable;

    private const ATTEMPTS = 3;

    private const TIMEOUT_SECONDS = 8;

    /** Seconds to wait before each retry. */
    private const BACKOFF = [1, 3];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private readonly int $webhookId,
        private readonly string $event,
        private readonly array $payload,
        private readonly ?int $redeliverOf = null,
    ) {}

    public function handle(): void
    {
        $webhook = Webhook::query()->find($this->webhookId);

        if ($webhook === null || ! $webhook->is_active) {
            return;
        }

        $body = json_encode([
            'event' => $this->event,
            'site' => config('site.name'),
            'sent_at' => now()->toIso8601String(),
            'data' => $this->payload,
        ], JSON_UNESCAPED_SLASHES);

        $timestamp = now()->getTimestamp();

        $statusCode = null;
        $error = null;
        $succeeded = false;
        $attempt = 0;

        while ($attempt < self::ATTEMPTS && ! $succeeded) {
            $attempt++;

            try {
                $signature = $webhook->sign($body, $timestamp);
                $timestampHeader = (string) $timestamp;

                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    Webhook::SIGNATURE_HEADER => $signature,
                    Webhook::TIMESTAMP_HEADER => $timestampHeader,
                ])
                    ->timeout(self::TIMEOUT_SECONDS)
                    ->withBody($body, 'application/json')
                    ->post($webhook->url);

                $statusCode = $response->status();
                $succeeded = $response->successful();
                $error = $succeeded ? null : 'The endpoint answered '.$statusCode.'.';
            } catch (Throwable $e) {
                $statusCode = null;
                $error = $e->getMessage();
            }

            if (! $succeeded && $attempt < self::ATTEMPTS && ! app()->runningUnitTests()) {
                sleep(self::BACKOFF[$attempt - 1] ?? 3);
            }
        }

        $this->record($webhook, $statusCode, $attempt, $error, $succeeded);
    }

    private function record(Webhook $webhook, ?int $statusCode, int $attempts, ?string $error, bool $succeeded): void
    {
        WebhookDelivery::query()->create([
            'webhook_id' => $webhook->id,
            'event' => $this->event,
            'payload' => $this->payload,
            'status_code' => $statusCode,
            'attempts' => $attempts,
            'error' => $error === null ? null : mb_substr($error, 0, 500),
            'succeeded' => $succeeded,
        ]);

        if ($succeeded) {
            $webhook->forceFill(['last_delivered_at' => now()])->save();
        }
    }
}
