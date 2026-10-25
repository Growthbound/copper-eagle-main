<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The record of one attempt to deliver an event.
 *
 * Kept whether it succeeded or not, so a failure is visible in the admin and
 * can be sent again without the original event being reconstructed by hand.
 */
#[Fillable(['webhook_id', 'event', 'payload', 'status_code', 'attempts', 'error', 'succeeded'])]
class WebhookDelivery extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'succeeded' => 'boolean',
        ];
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    public function summary(): string
    {
        if ($this->succeeded) {
            return 'Delivered'.($this->status_code ? ' ('.$this->status_code.')' : '');
        }

        if ($this->status_code !== null) {
            return 'Rejected with '.$this->status_code;
        }

        return 'Could not connect';
    }
}
