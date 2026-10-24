<?php

namespace Tests\Feature\Api;

use App\Content\Enums\ApiKeyScope;
use App\Models\ContentEntry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Concerns\InteractsWithContentApi;
use Tests\TestCase;

class ContentLifecycleApiTest extends TestCase
{
    use InteractsWithContentApi;
    use RefreshDatabase;

    public function test_preview_publish_schedule_cancel_and_rollback_use_governed_lifecycle(): void
    {
        CarbonImmutable::setTestNow('2026-07-25 10:00:00');
        $this->authenticateContentApi(ApiKeyScope::cases());
        $created = $this->contentPost(
            '/api/content/v1/entries',
            $this->standardEntryPayload(),
            $this->idempotencyHeaders('lifecycle-create-20260725'),
        )->assertCreated()->json('data');
        $entryId = $created['id'];
        $revisionId = $created['latest_revision']['id'];

        $this->contentPost(
            "/api/content/v1/entries/{$entryId}/preview",
            ['revision_id' => $revisionId],
            $this->idempotencyHeaders('lifecycle-preview-20260725', 0),
        )->assertCreated()
            ->assertJsonStructure(['data' => ['url', 'expires_at']]);

        $this->contentPost(
            "/api/content/v1/entries/{$entryId}/publish",
            ['revision_id' => $revisionId],
            $this->idempotencyHeaders('lifecycle-publish-20260725', 0),
        )->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.lock_version', 1);

        $this->contentPost(
            "/api/content/v1/entries/{$entryId}/schedule",
            [
                'revision_id' => $revisionId,
                'publish_at' => '2026-07-26T10:00:00-06:00',
            ],
            $this->idempotencyHeaders('lifecycle-schedule-20260725', 1),
        )->assertOk()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.lock_version', 2);

        $this->contentDelete(
            "/api/content/v1/entries/{$entryId}/schedule",
            [],
            $this->idempotencyHeaders('lifecycle-cancel-20260725', 2),
        )->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.lock_version', 3);

        $changed = $this->standardEntryPayload();
        $changed['payload']['blocks'][0]['props']['answer'][0]['text'] = 'A newer published answer.';
        $revisionTwo = $this->contentPost(
            "/api/content/v1/entries/{$entryId}/revisions",
            ['payload' => $changed['payload']],
            $this->idempotencyHeaders('lifecycle-revise-20260725', 3),
        )->assertCreated()->json('data.id');

        $this->contentPost(
            "/api/content/v1/entries/{$entryId}/publish",
            ['revision_id' => $revisionTwo],
            $this->idempotencyHeaders('lifecycle-republish-20260725', 4),
        )->assertOk()
            ->assertJsonPath('data.lock_version', 5);

        $this->contentPost(
            "/api/content/v1/entries/{$entryId}/rollback",
            ['revision_id' => $revisionId],
            $this->idempotencyHeaders('lifecycle-rollback-20260725', 5),
        )->assertCreated()
            ->assertJsonPath('data.revision_number', 3);

        $entry = ContentEntry::query()->findOrFail($entryId);
        $this->assertSame(6, $entry->lock_version);
        $this->assertSame(3, $entry->publishedRevision->revision_number);
        $this->assertDatabaseHas('content_audit_events', [
            'content_entry_id' => $entryId,
            'action' => 'rollback',
            'request_id' => 'request-content-api-001',
        ]);
        $this->assertNotNull($entry->auditEvents()->where('action', 'rollback')->sole()->api_key_id);
    }

    public function test_lifecycle_scope_is_enforced_before_entry_resolution(): void
    {
        $this->authenticateContentApi([ApiKeyScope::ContentRead]);

        $this->contentPost(
            '/api/content/v1/entries/999999/publish',
            ['revision_id' => 999999],
            $this->idempotencyHeaders('scope-before-binding-20260725', 0),
        )->assertForbidden()
            ->assertJsonPath('code', 'missing_scope');
    }

    public function test_manual_lifecycle_validation_is_inside_the_idempotency_boundary(): void
    {
        $this->authenticateContentApi(ApiKeyScope::cases());
        $created = $this->contentPost(
            '/api/content/v1/entries',
            $this->standardEntryPayload('manual-idempotency'),
            $this->idempotencyHeaders('manual-idempotency-create'),
        )->assertCreated()->json('data');
        $headers = $this->idempotencyHeaders(
            'manual-preview-validation-20260726',
            0,
        );

        $this->contentPost(
            "/api/content/v1/entries/{$created['id']}/preview",
            [],
            $headers,
        )->assertUnprocessable()
            ->assertJsonPath('code', 'request_validation_failed');

        $this->contentPost(
            "/api/content/v1/entries/{$created['id']}/preview",
            ['revision_id' => $created['latest_revision']['id']],
            $headers,
        )->assertConflict()
            ->assertJsonPath('code', 'idempotency_conflict');

        $this->assertDatabaseCount('content_preview_tokens', 0);
    }

    public function test_schedule_requires_a_timezone_aware_iso_8601_timestamp(): void
    {
        $this->authenticateContentApi(ApiKeyScope::cases());
        $created = $this->contentPost(
            '/api/content/v1/entries',
            $this->standardEntryPayload(),
            $this->idempotencyHeaders('strict-schedule-create'),
        )->assertCreated()->json('data');

        foreach ([
            '2026-07-26T10:00:00',
            'tomorrow',
            '2026-07-26',
        ] as $index => $publishAt) {
            $this->contentPost(
                "/api/content/v1/entries/{$created['id']}/schedule",
                [
                    'revision_id' => $created['latest_revision']['id'],
                    'publish_at' => $publishAt,
                ],
                $this->idempotencyHeaders("strict-schedule-{$index}", 0),
            )->assertUnprocessable()
                ->assertJsonStructure(['errors' => ['/publish_at']]);
        }

        $this->assertNull(ContentEntry::query()->findOrFail($created['id'])->scheduled_for);
    }
}
