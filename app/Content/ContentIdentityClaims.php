<?php

namespace App\Content;

use App\Content\Validation\ContentValidationResult;
use App\Models\ContentEntry;
use Illuminate\Support\Facades\DB;

final class ContentIdentityClaims
{
    /** @param array<string, mixed> $identity */
    public function availability(
        ?ContentEntry $entry,
        array $identity,
    ): ContentValidationResult {
        $query = DB::table('content_identity_claims')
            ->where('kind', (string) $identity['kind'])
            ->where('slug', (string) $identity['slug']);

        if ($entry !== null) {
            $query->where('content_entry_id', '!=', $entry->id);
        }

        return new ContentValidationResult($query->exists() ? [
            '/identity/slug' => ['This public path is already owned by another content entry.'],
        ] : []);
    }

    /** @param array<string, mixed> $identity */
    public function claimCurrent(ContentEntry $entry, array $identity): void
    {
        DB::table('content_identity_claims')->insert([
            'content_entry_id' => $entry->id,
            'claim_type' => 'current',
            'kind' => (string) $identity['kind'],
            'slug' => (string) $identity['slug'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $identity */
    public function moveCurrent(ContentEntry $entry, array $identity): void
    {
        $kind = (string) $identity['kind'];
        $slug = (string) $identity['slug'];
        $scheduledIdentity = $this->scheduledIdentity($entry);

        DB::table('content_identity_claims')
            ->where('content_entry_id', $entry->id)
            ->where('claim_type', 'scheduled')
            ->where('kind', $kind)
            ->where('slug', $slug)
            ->delete();

        $current = DB::table('content_identity_claims')
            ->where('content_entry_id', $entry->id)
            ->where('claim_type', 'current')
            ->first(['id']);

        if ($current === null) {
            $this->claimCurrent($entry, $identity);
            $this->restoreScheduledClaim($entry, $scheduledIdentity);

            return;
        }

        DB::table('content_identity_claims')
            ->where('id', $current->id)
            ->update([
                'kind' => $kind,
                'slug' => $slug,
                'updated_at' => now(),
            ]);

        $this->restoreScheduledClaim($entry, $scheduledIdentity);
    }

    /** @param array<string, mixed> $identity */
    public function reserveScheduled(ContentEntry $entry, array $identity): void
    {
        $this->releaseScheduled($entry);

        $current = DB::table('content_identity_claims')
            ->where('content_entry_id', $entry->id)
            ->where('claim_type', 'current')
            ->first(['kind', 'slug']);

        if ($current !== null
            && $current->kind === (string) $identity['kind']
            && $current->slug === (string) $identity['slug']) {
            return;
        }

        DB::table('content_identity_claims')->insert([
            'content_entry_id' => $entry->id,
            'claim_type' => 'scheduled',
            'kind' => (string) $identity['kind'],
            'slug' => (string) $identity['slug'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function releaseScheduled(ContentEntry $entry): void
    {
        DB::table('content_identity_claims')
            ->where('content_entry_id', $entry->id)
            ->where('claim_type', 'scheduled')
            ->delete();
    }

    /** @param array<string, mixed> $identity */
    public function promote(ContentEntry $entry, array $identity): void
    {
        $desiredKind = (string) $identity['kind'];
        $desiredSlug = (string) $identity['slug'];
        $current = DB::table('content_identity_claims')
            ->where('content_entry_id', $entry->id)
            ->where('claim_type', 'current')
            ->first(['id', 'kind', 'slug']);

        if ($current !== null
            && $current->kind === $desiredKind
            && $current->slug === $desiredSlug) {
            $this->releaseScheduled($entry);

            return;
        }

        $destination = DB::table('content_identity_claims')
            ->where('content_entry_id', $entry->id)
            ->where('claim_type', 'scheduled')
            ->where('kind', $desiredKind)
            ->where('slug', $desiredSlug)
            ->first(['id']);

        if ($destination === null) {
            $this->reserveScheduled($entry, $identity);
            $destination = DB::table('content_identity_claims')
                ->where('content_entry_id', $entry->id)
                ->where('claim_type', 'scheduled')
                ->where('kind', $desiredKind)
                ->where('slug', $desiredSlug)
                ->firstOrFail(['id']);
        }

        DB::table('content_identity_claims')
            ->where('content_entry_id', $entry->id)
            ->where('claim_type', 'current')
            ->delete();

        DB::table('content_identity_claims')
            ->where('id', $destination->id)
            ->update([
                'claim_type' => 'current',
                'updated_at' => now(),
            ]);

        $this->releaseScheduled($entry);
    }

    /**
     * @return array{kind: string, slug: string}|null
     */
    private function scheduledIdentity(ContentEntry $entry): ?array
    {
        if (! is_string($entry->scheduled_kind)
            || $entry->scheduled_kind === ''
            || ! is_string($entry->scheduled_slug)
            || $entry->scheduled_slug === '') {
            return null;
        }

        return [
            'kind' => $entry->scheduled_kind,
            'slug' => $entry->scheduled_slug,
        ];
    }

    /**
     * @param  array{kind: string, slug: string}|null  $identity
     */
    private function restoreScheduledClaim(
        ContentEntry $entry,
        ?array $identity,
    ): void {
        if ($identity !== null) {
            $this->reserveScheduled($entry, $identity);
        }
    }
}
