<?php

namespace Tests\Feature;

use App\Content\ContentLifecycle;
use App\Content\Enums\ContentKind;
use App\Content\Enums\PageTemplate;
use App\Models\ApiIdempotencyRecord;
use App\Models\User;
use App\Services\ApiKeyManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_retention_command_prunes_only_expired_or_revoked_ephemeral_records(): void
    {
        $owner = User::factory()->create();
        $key = app(ApiKeyManager::class)->create($owner, 'Retention test')->key;

        $activeIdempotency = $this->idempotency($key->id, 'active', now()->addHour());
        $expiredIdempotency = $this->idempotency($key->id, 'expired', now()->subSecond());

        $entry = app(ContentLifecycle::class)->createDraft(
            [
                'kind' => ContentKind::Page,
                'slug' => 'retention-test',
                'title' => 'Retention test',
                'template' => PageTemplate::Standard,
            ],
            [
                'title' => 'Retention test',
                'blocks' => [[
                    'type' => 'content/direct-answer',
                    'props' => [
                        'heading' => 'What is retained?',
                        'answer' => [['text' => 'Only active preview credentials.']],
                    ],
                ]],
            ],
            $owner,
        );
        $revision = $entry->revisions()->sole();
        $activePreview = app(ContentLifecycle::class)->issuePreview($entry, $revision, 0, $owner)->preview;
        $expiredPreview = app(ContentLifecycle::class)->issuePreview($entry, $revision, 0, $owner)->preview;
        $revokedPreview = app(ContentLifecycle::class)->issuePreview($entry, $revision, 0, $owner)->preview;
        $expiredPreview->update(['expires_at' => now()->subSecond()]);
        $revokedPreview->update(['revoked_at' => now()]);

        $this->artisan('content:prune-expired')->assertSuccessful();

        $this->assertDatabaseHas('api_idempotency_records', ['id' => $activeIdempotency->id]);
        $this->assertDatabaseMissing('api_idempotency_records', ['id' => $expiredIdempotency->id]);
        $this->assertDatabaseHas('content_preview_tokens', ['id' => $activePreview->id]);
        $this->assertDatabaseMissing('content_preview_tokens', ['id' => $expiredPreview->id]);
        $this->assertDatabaseMissing('content_preview_tokens', ['id' => $revokedPreview->id]);
    }

    private function idempotency(int $apiKeyId, string $operation, \DateTimeInterface $expiresAt): ApiIdempotencyRecord
    {
        return ApiIdempotencyRecord::query()->create([
            'api_key_id' => $apiKeyId,
            'operation' => $operation,
            'idempotency_key_hash' => hash('sha256', $operation.'-key'),
            'request_hash' => hash('sha256', $operation.'-request'),
            'response_status' => 200,
            'response_body' => ['ok' => true],
            'expires_at' => $expiresAt,
        ]);
    }
}
