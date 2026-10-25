<?php

namespace App\Webhooks;

use App\Jobs\SendWebhook;
use App\Models\Lead;
use App\Models\Webhook;

/**
 * Fans one site event out to every active destination.
 *
 * Delivery is queued after the response, so a slow or unreachable endpoint
 * never delays the visitor who submitted the form.
 */
class WebhookDispatcher
{
    public const LEAD_CREATED = 'lead.created';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(string $event, array $payload): void
    {
        Webhook::query()
            ->where('is_active', true)
            ->get()
            ->each(fn (Webhook $webhook) => SendWebhook::dispatch($webhook->id, $event, $payload)->afterResponse());
    }

    public function leadCreated(Lead $lead): void
    {
        $this->dispatch(self::LEAD_CREATED, self::leadPayload($lead));
    }

    /**
     * The shape receivers depend on. Keys are stable; add rather than rename.
     *
     * @return array<string, mixed>
     */
    public static function leadPayload(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'name' => $lead->name,
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'service' => $lead->service,
            'message' => $lead->message,
            'status' => $lead->status,
            'received_at' => $lead->created_at?->toIso8601String(),
            'consent' => [
                'given' => (bool) $lead->consent_given,
                'text' => $lead->consent_text,
                'at' => $lead->consent_at?->toIso8601String(),
                'ip' => $lead->consent_ip,
                // Each separately asked permission; null where it was not asked.
                'choices' => $lead->consentChoices(),
            ],
            'source' => $lead->source(),
            'attribution' => $lead->attribution(),
        ];
    }
}
