<?php

namespace App\Models;

use App\HighLevel\LeadSync;
use App\Jobs\NotifyOfLead;
use App\Support\Attribution;
use App\Webhooks\WebhookDispatcher;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'name', 'first_name', 'last_name', 'email', 'phone', 'service', 'message',
    'consent_given', 'consent_text', 'consent_at', 'consent_ip',
    'consent_service', 'consent_marketing',
])]
class Lead extends Model
{
    public const STATUSES = ['new', 'handled', 'archived'];

    /** Where the visitor came from, captured before they reached the form. */
    public const ATTRIBUTION_FIELDS = [
        ...Attribution::UTM_FIELDS,
        ...Attribution::CLICK_FIELDS,
        ...Attribution::CONTEXT_FIELDS,
    ];

    /**
     * Mirrors the column default so a freshly created lead reports its status
     * without being reloaded. Webhook payloads are built from the in-memory
     * model, and would otherwise send a null status.
     */
    protected $attributes = [
        'status' => 'new',
    ];

    /**
     * Fires from the model rather than from the form, so every way a lead can
     * arrive — the public form now, an import or a second form later — reaches
     * the configured destinations without being wired up again.
     */
    protected static function booted(): void
    {
        // Stamped here rather than in the form so any future entry point —
        // a second form, a phone-capture widget — is attributed too.
        static::creating(function (Lead $lead): void {
            $stored = Attribution::stored();

            if ($stored === []) {
                return;
            }

            foreach (self::ATTRIBUTION_FIELDS as $field) {
                if (blank($lead->{$field})) {
                    $lead->{$field} = $stored[$field] ?? null;
                }
            }
        });

        static::created(function (Lead $lead): void {
            app(LeadSync::class)->enqueue($lead);
            app(WebhookDispatcher::class)->leadCreated($lead);

            NotifyOfLead::dispatch($lead->id)->afterResponse();
        });
    }

    public function highLevelDelivery(): HasOne
    {
        return $this->hasOne(HighLevelDelivery::class);
    }

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
            'consent_given' => 'boolean',
            'consent_at' => 'datetime',
            // Null when the form did not ask; see LeadConsent::CHOICE_KINDS.
            'consent_service' => 'boolean',
            'consent_marketing' => 'boolean',
        ];
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if ($status === null || ! in_array($status, self::STATUSES, true)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            foreach (['name', 'email', 'phone', 'message'] as $column) {
                $query->orWhere($column, 'like', '%'.$term.'%');
            }
        });
    }

    /**
     * @return array<string, string|null>
     */
    public function attribution(): array
    {
        return collect(self::ATTRIBUTION_FIELDS)
            ->mapWithKeys(fn (string $field): array => [$field => $this->{$field}])
            ->all();
    }

    /**
     * A short description of where this enquiry came from.
     */
    public function source(): string
    {
        return Attribution::describe($this->attribution());
    }

    /**
     * Each separately asked text-message permission, or null where the form
     * that took this lead did not ask it.
     *
     * @return array<string, bool|null>
     */
    public function consentChoices(): array
    {
        return [
            'service' => $this->consent_service,
            'marketing' => $this->consent_marketing,
        ];
    }

    public function markAs(string $status): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            return;
        }

        $this->forceFill([
            'status' => $status,
            'handled_at' => $status === 'new' ? null : ($this->handled_at ?? now()),
        ])->save();
    }
}
