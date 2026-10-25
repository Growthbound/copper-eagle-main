<?php

namespace App\Services\Referrals;

use App\Models\Referral;
use Illuminate\Support\Str;
use Throwable;

class ReferralSync
{
    public function __construct(private HighLevelClient $client, private ReferralFieldMap $fieldMap) {}

    public function configurationProblem(): ?string
    {
        if (! $this->client->configured()) {
            return 'HighLevel connection is not configured. Registrations remain saved locally.';
        }

        return null;
    }

    public function sync(Referral $referral): bool
    {
        $referral->refresh();
        if ($referral->sync_status === 'synced') {
            return true;
        }
        if (! $referral->registered_at || in_array($referral->sync_status, ['syncing', 'uncertain'], true)) {
            return false;
        }
        $problem = $this->configurationProblem();
        if ($referral->highlevel_location_id && $referral->highlevel_location_id !== config('referrals.highlevel.location_id')) {
            $problem = 'This affiliate belongs to a different HighLevel location. Restore the original connection.';
        }
        if (! $referral->main_contact_id) {
            $problem = 'This older registration has no '.config('site.name').' main contact. Keep it local until reviewed.';
        }
        if ($problem) {
            $referral->forceFill(['sync_message' => $problem])->save();

            return false;
        }
        try {
            $fields = $this->fieldMap->resolve((bool) $referral->service_areas);
        } catch (ReferralFieldMappingException $exception) {
            $referral->forceFill(['sync_message' => $exception->getMessage()])->save();

            return false;
        }
        // Database compare-and-swap also protects overlapping admin/registration requests.
        $attempt = (string) Str::uuid();
        $claimed = Referral::whereKey($referral->id)->whereIn('sync_status', ['pending', 'failed'])->update([
            'sync_attempt_id' => $attempt, 'sync_started_at' => now(),
            'sync_status' => 'syncing', 'sync_message' => 'Sync started. If interrupted, reconcile before retrying.',
            'highlevel_location_id' => config('referrals.highlevel.location_id'),
        ]);
        if (! $claimed) {
            return false;
        }
        $referral->sync_attempt_id = $attempt;
        $values = ['code' => $referral->code, 'source' => $referral->source(), 'business' => $referral->business_name,
            'main_contact' => $referral->main_contact_name, 'url' => $referral->publicUrl()];
        $customFields = [];
        foreach ($values as $key => $value) {
            $customFields[] = ['id' => $fields[$key], 'fieldValue' => $value];
        }
        if ($referral->service_areas) {
            $customFields[] = ['id' => $fields['service_areas'], 'fieldValue' => $referral->service_areas];
        }
        try {
            $payload = [
                'locationId' => config('referrals.highlevel.location_id'),
                'firstName' => $referral->first_name, 'lastName' => $referral->last_name,
                'name' => $referral->name, 'companyName' => $referral->business_name,
                'assignedTo' => $referral->main_contact_id, 'source' => config('site.name').' Affiliate Program',
                'customFields' => $customFields,
            ];
            if ($referral->phone) {
                $payload['phone'] = $referral->phone;
            }
            if ($referral->email) {
                $payload['email'] = $referral->email;
            }
            $response = $this->client->createContact($payload);
        } catch (Throwable) {
            $this->mark($referral, 'uncertain', 'HighLevel did not confirm the result. Check for the contact and reconcile its ID; do not create again.');

            return false;
        }
        $contact = $response->json('contact');
        if ($response->successful() && is_array($contact) && is_string($contact['id'] ?? null)
            && $contact['id'] !== '' && ($contact['locationId'] ?? null) === config('referrals.highlevel.location_id')) {
            $this->mark($referral, 'synced', null, ['highlevel_contact_id' => $contact['id'], 'synced_at' => now()]);

            return true;
        }
        $uncertain = $response->successful() || $response->serverError() || $response->status() === 408;
        $this->mark($referral, $uncertain ? 'uncertain' : 'failed', 'HighLevel returned HTTP '.$response->status().'. '
            .($uncertain ? 'Check for the contact and reconcile its ID before any retry.' : 'Correct the connection or field configuration, then retry.'));

        return false;
    }

    public function reconcile(Referral $referral, string $contactId): bool
    {
        $referral->refresh();
        if (! $referral->registered_at || ! preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $contactId)
            || $this->configurationProblem() || ! in_array($referral->sync_status, ['uncertain', 'syncing'], true)
            || $referral->highlevel_location_id !== config('referrals.highlevel.location_id')) {
            return false;
        }
        if ($referral->sync_status === 'syncing' && ($referral->sync_started_at ?? $referral->updated_at)->gt(now()->subMinutes(2))) {
            return false;
        }
        try {
            $fields = $this->fieldMap->resolve(false);
            $response = $this->client->contact($contactId);
            $contact = $response->json('contact');
        } catch (Throwable) {
            return false;
        }
        if (! $response->successful() || ! is_array($contact) || ($contact['id'] ?? null) !== $contactId
            || ($contact['locationId'] ?? null) !== $referral->highlevel_location_id) {
            return false;
        }
        $remoteCode = collect($contact['customFields'] ?? [])->firstWhere('id', $fields['code']);
        if (($remoteCode['value'] ?? null) !== $referral->code) {
            return false;
        }

        return (bool) Referral::whereKey($referral->id)->where('sync_attempt_id', $referral->sync_attempt_id)->whereIn('sync_status', ['uncertain', 'syncing'])->update([
            'sync_status' => 'synced', 'highlevel_contact_id' => $contactId, 'synced_at' => now(), 'sync_message' => null,
        ]);
    }

    private function mark(Referral $referral, string $status, ?string $message, array $extra = []): void
    {
        Referral::whereKey($referral->id)->where('sync_status', 'syncing')
            ->where('sync_attempt_id', $referral->sync_attempt_id)
            ->update(['sync_status' => $status, 'sync_message' => $message, ...$extra]);
    }
}
