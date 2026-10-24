<?php

namespace App\Content;

use App\Models\ContentEntry;
use App\Models\ContentPreviewToken;
use App\Models\ContentRevision;
use App\Models\User;

final class ContentPreviewService
{
    public function issue(
        ContentEntry $entry,
        ContentRevision $revision,
        User $actor,
    ): IssuedPreview {
        $token = bin2hex(random_bytes(32));
        $preview = ContentPreviewToken::create([
            'content_entry_id' => $entry->id,
            'content_revision_id' => $revision->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHour(),
            'created_by' => $actor->id,
        ]);

        return new IssuedPreview($preview, $token);
    }

    public function resolve(string $token): ?ResolvedContentPreview
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
            return null;
        }

        $preview = ContentPreviewToken::query()
            ->with(['entry', 'revision'])
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($preview === null
            || $preview->revision === null
            || $preview->entry === null
            || $preview->revision->content_entry_id !== $preview->entry->id) {
            return null;
        }

        return new ResolvedContentPreview($preview->entry, $preview->revision);
    }
}
