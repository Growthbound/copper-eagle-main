<?php

namespace App\HighLevel;

use App\Jobs\SyncLeadToHighLevel;
use App\Models\HighLevelConnection;
use App\Models\HighLevelDelivery;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class LeadSync
{
    public function enqueue(Lead $lead): ?HighLevelDelivery
    {
        $delivery = DB::transaction(function () use ($lead): ?HighLevelDelivery {
            $connection = HighLevelConnection::query()->lockForUpdate()->find(1);
            if (! $connection) {
                return null;
            }

            return HighLevelDelivery::query()->firstOrCreate(['lead_id' => $lead->id], [
                'location_id' => $connection->location_id, 'reference' => (string) Str::uuid(), 'status' => 'pending',
            ]);
        });

        if ($delivery && HighLevelConnection::current()?->enabled) {
            SyncLeadToHighLevel::dispatch($delivery->id)->afterResponse();
        }

        return $delivery;
    }

    public function send(int $id, bool $manual = false, bool $reconcile = false): void
    {
        $connection = HighLevelConnection::current();
        if (! $connection?->enabled) {
            return;
        }

        $worker = (string) Str::uuid();
        $query = HighLevelDelivery::query()->whereKey($id)->where('location_id', $connection->location_id)
            ->whereNull('synced_at')->where(fn ($q) => $q->whereNull('locked_until')->orWhere('locked_until', '<=', now()));
        if (! $manual) {
            $query->where('status', 'pending')->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()));
        }
        if (! $query->update(['worker_token' => $worker, 'locked_until' => now()->addMinutes(5)])) {
            return;
        }

        $delivery = HighLevelDelivery::query()->with('lead')->find($id);
        if (! $delivery?->lead) {
            return;
        }

        $write = function (array $values) use ($delivery, $worker): void {
            $changed = HighLevelDelivery::query()->whereKey($delivery->id)->where('worker_token', $worker)->update($values);
            if (! $changed) {
                throw new Failure('Delivery changed while sending. Check its current status.');
            }
            $delivery->forceFill($values);
        };

        try {
            $write(['attempts' => $delivery->attempts + 1, 'error' => null]);
            if ($delivery->note_started_at && ! $reconcile) {
                throw new Failure('A note may already exist in HighLevel. Check delivery before sending again.', false, true);
            }
            $client = new Client($connection->token ?? '');
            if (! $delivery->contact_id) {
                $lead = $delivery->lead;
                if (blank($lead->email) && blank($lead->phone)) {
                    throw new Failure('This lead needs an email address or phone number.');
                }
                $result = $client->request('POST', '/contacts/upsert', array_filter([
                    'locationId' => $delivery->location_id, 'name' => $lead->name,
                    'email' => $lead->email, 'phone' => $lead->phone, 'source' => $lead->source(),
                    'createNewIfDuplicateAllowed' => false,
                ], fn ($value) => $value !== null && $value !== ''));
                $contact = $this->contact($result, $delivery->location_id);
                $write(['contact_id' => $contact]);
            } else {
                // Recheck the account before resuming a partial delivery.
                $this->contact($client->request('GET', '/contacts/'.Client::identifier($delivery->contact_id)), $delivery->location_id, $delivery->contact_id);
            }

            if (! $delivery->tagged_at) {
                $result = $client->request('POST', '/contacts/'.$delivery->contact_id.'/tags', ['tags' => ['Website Lead']]);
                if (! is_array($result['tags'] ?? null) || ! collect($result['tags'])->contains(fn ($tag) => is_string($tag) && strtolower($tag) === 'website lead')) {
                    throw new Failure('HighLevel did not confirm the Website Lead tag.', true);
                }
                $write(['tagged_at' => now()]);
            }

            if ($delivery->note_started_at) {
                $result = $client->request('GET', '/contacts/'.$delivery->contact_id.'/notes');
                if (! is_array($result['notes'] ?? null)) {
                    throw new Failure('HighLevel did not return its notes. Check the contact manually.', false, true);
                }
                $notes = collect($result['notes'])->filter(fn ($note) => is_array($note) && is_string($note['body'] ?? null)
                    && str_starts_with($note['body'], $this->marker($delivery)."\n"));
                if ($notes->count() !== 1) {
                    throw new Failure('Could not confirm exactly one matching note. Inspect this contact in HighLevel.', false, true);
                }
                $write(['note_id' => Client::identifier($notes->first()['id'] ?? null)]);
            } elseif (! $delivery->note_id) {
                // Persist before POST: a crash or lost response must never blindly create another note.
                $write(['note_started_at' => now()]);
                try {
                    $result = $client->request('POST', '/contacts/'.$delivery->contact_id.'/notes', ['body' => $this->note($delivery)]);
                } catch (Failure $e) {
                    if (! $e->ambiguous) {
                        $write(['note_started_at' => null]);
                    }
                    throw $e;
                }
                $note = $result['note'] ?? null;
                if (! is_array($note) || ($note['contactId'] ?? null) !== $delivery->contact_id) {
                    throw new Failure('HighLevel did not confirm the note for this contact.', false, true);
                }
                $write(['note_id' => Client::identifier($note['id'] ?? null)]);
            }

            $write(['status' => 'synced', 'synced_at' => now(), 'next_attempt_at' => null, 'error' => null]);
        } catch (Throwable $e) {
            $known = $e instanceof Failure;
            $ambiguous = $delivery->note_started_at !== null;
            $retry = $known && $e->retryable && ! $ambiguous && $delivery->attempts < 5;
            $write([
                'status' => $ambiguous ? 'needs_review' : ($retry ? 'pending' : 'failed'),
                'error' => $known ? $e->getMessage() : 'Delivery stopped unexpectedly. Check the connection and retry.',
                'next_attempt_at' => $retry ? now()->addMinutes(2 ** $delivery->attempts) : null,
                // Explicitly rejected note requests may be retried. Lost outcomes require readback.
                'note_started_at' => $ambiguous ? $delivery->note_started_at : null,
            ]);
        } finally {
            HighLevelDelivery::query()->whereKey($id)->where('worker_token', $worker)->update(['worker_token' => null, 'locked_until' => null]);
        }
    }

    public function reconcile(int $id): void
    {
        $this->send($id, manual: true, reconcile: true);
    }

    public function retryMissingNote(int $id): void
    {
        HighLevelDelivery::query()->whereKey($id)->where('status', 'needs_review')
            ->whereNull('worker_token')->whereNull('note_id')->update(['note_started_at' => null, 'status' => 'failed']);
        $this->send($id, manual: true);
    }

    private function contact(array $result, string $location, ?string $expected = null): string
    {
        $contact = $result['contact'] ?? null;
        if (! is_array($contact) || ($contact['locationId'] ?? null) !== $location || ($expected && ($contact['id'] ?? null) !== $expected)) {
            throw new Failure('HighLevel returned a contact outside the verified location. Delivery stopped.');
        }

        return Client::identifier($contact['id'] ?? null);
    }

    private function marker(HighLevelDelivery $delivery): string
    {
        return 'Website submission '.$delivery->reference;
    }

    private function note(HighLevelDelivery $delivery): string
    {
        $lead = $delivery->lead;
        $lines = [$this->marker($delivery), 'Site: '.config('site.name'), 'Received: '.$lead->created_at?->toIso8601String(),
            'Service: '.($lead->service ?: 'Not selected'), 'Source: '.$lead->source(),
            'Consent: '.($lead->consent_given ? 'agreed' : 'not recorded')];
        if ($lead->consent_at) {
            $lines[] = 'Consent at: '.$lead->consent_at->toIso8601String();
        }
        if ($lead->consent_text) {
            $lines[] = 'Consent wording: '.$lead->consent_text;
        }
        foreach ($lead->attribution() as $key => $value) {
            if (filled($value)) {
                $lines[] = $key.': '.$value;
            }
        }
        $lines[] = "\nRequest:\n".($lead->message ?: 'No message supplied');

        return implode("\n", $lines);
    }
}
