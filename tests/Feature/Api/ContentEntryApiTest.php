<?php

namespace Tests\Feature\Api;

use App\Content\Enums\ApiKeyScope;
use App\Models\ContentEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Concerns\InteractsWithContentApi;
use Tests\TestCase;

class ContentEntryApiTest extends TestCase
{
    use InteractsWithContentApi;
    use RefreshDatabase;

    public function test_hermes_can_create_and_replay_an_attributed_draft(): void
    {
        $this->authenticateContentApi([
            ApiKeyScope::ContentRead,
            ApiKeyScope::ContentDraft,
        ]);
        $headers = $this->idempotencyHeaders('create-about-20260725');

        $first = $this->contentPost(
            '/api/content/v1/entries',
            $this->standardEntryPayload(),
            $headers,
        )->assertCreated()
            ->assertHeader('X-Request-ID', 'request-content-api-001')
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('data.slug', 'about')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.lock_version', 0)
            ->assertJsonPath('data.latest_revision.revision_number', 1);

        $second = $this->contentPost(
            '/api/content/v1/entries',
            $this->standardEntryPayload(),
            $headers,
        )->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame($first->json(), $second->json());
        $this->assertDatabaseCount('content_entries', 1);
        $this->assertDatabaseCount('content_revisions', 1);
        $this->assertDatabaseHas('content_audit_events', [
            'action' => 'create',
            'request_id' => 'request-content-api-001',
        ]);
        $this->assertNotNull(ContentEntry::query()->sole()->auditEvents()->sole()->api_key_id);
    }

    public function test_entry_validation_errors_use_json_pointers_and_a_stable_envelope(): void
    {
        $this->authenticateContentApi([ApiKeyScope::ContentDraft]);
        $payload = $this->standardEntryPayload();
        $payload['payload']['raw_html'] = '<script>alert(1)</script>';

        $this->contentPost(
            '/api/content/v1/entries',
            $payload,
            $this->idempotencyHeaders('invalid-about-20260725'),
        )->assertUnprocessable()
            ->assertHeader('X-Request-ID', 'request-content-api-001')
            ->assertJsonPath('code', 'content_validation_failed')
            ->assertJsonPath('request_id', 'request-content-api-001')
            ->assertJsonStructure(['message', 'code', 'request_id', 'errors'])
            ->assertJsonPath(
                'errors./raw_html.0',
                'Managed content cannot choose markup, code, classes, or component paths.',
            );
    }

    public function test_request_validation_failures_are_idempotent_before_controller_dispatch(): void
    {
        $this->authenticateContentApi([ApiKeyScope::ContentDraft]);
        $headers = $this->idempotencyHeaders('invalid-request-boundary-20260726');
        $invalid = $this->standardEntryPayload();
        unset($invalid['identity']);

        $this->contentPost('/api/content/v1/entries', $invalid, $headers)
            ->assertUnprocessable()
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('code', 'request_validation_failed');

        $this->contentPost(
            '/api/content/v1/entries',
            $this->standardEntryPayload(),
            $headers,
        )->assertConflict()
            ->assertJsonPath('code', 'idempotency_conflict');

        $this->assertDatabaseCount('content_entries', 0);
        $this->assertDatabaseCount('api_idempotency_records', 1);
    }

    public function test_duplicate_entry_identity_returns_a_stable_validation_error(): void
    {
        $this->authenticateContentApi([ApiKeyScope::ContentDraft]);

        $this->contentPost(
            '/api/content/v1/entries',
            $this->standardEntryPayload('about'),
            $this->idempotencyHeaders('create-unique-about'),
        )->assertCreated();

        $this->contentPost(
            '/api/content/v1/entries',
            $this->standardEntryPayload('/about/'),
            $this->idempotencyHeaders('reject-duplicate-about'),
        )->assertUnprocessable()
            ->assertJsonPath('code', 'content_validation_failed')
            ->assertJsonPath(
                'errors./identity/slug.0',
                'This public path is already owned by another content entry.',
            );

        $this->assertDatabaseCount('content_entries', 1);
        $this->assertDatabaseCount('content_revisions', 1);
    }

    public function test_index_filters_entries_and_clamps_pagination_to_one_hundred(): void
    {
        $this->authenticateContentApi([
            ApiKeyScope::ContentRead,
            ApiKeyScope::ContentDraft,
        ]);

        foreach (['about', 'service-overview', 'contact'] as $index => $slug) {
            $this->contentPost(
                '/api/content/v1/entries',
                $this->standardEntryPayload($slug),
                $this->idempotencyHeaders("create-{$slug}-20260725-{$index}"),
            )->assertCreated();
        }

        $response = $this->contentGet(
            '/api/content/v1/entries?kind=page&status=draft&per_page=2',
        )
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);

        foreach (['first', 'last', 'next'] as $relation) {
            parse_str(
                (string) parse_url((string) $response->json("links.{$relation}"), PHP_URL_QUERY),
                $query,
            );

            $this->assertSame('page', $query['kind']);
            $this->assertSame('draft', $query['status']);
            $this->assertSame('2', $query['per_page']);
        }

        $this->contentGet('/api/content/v1/entries?per_page=999')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);

        $this->contentGet('/api/content/v1/entries?kind=article')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->contentGet('/api/content/v1/entries?template=standard&slug=contact')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'contact');
    }

    public function test_hermes_can_validate_a_candidate_revision_without_persisting_it(): void
    {
        $this->authenticateContentApi([
            ApiKeyScope::ContentDraft,
        ]);
        $created = $this->contentPost(
            '/api/content/v1/entries',
            $this->standardEntryPayload(),
            $this->idempotencyHeaders('create-validation-target'),
        )->assertCreated()->json('data');
        $candidate = $this->standardEntryPayload();
        $candidate['payload']['raw_html'] = '<strong>unsafe</strong>';

        $this->contentPost(
            "/api/content/v1/entries/{$created['id']}/validate",
            ['payload' => $candidate['payload']],
            $this->idempotencyHeaders('validate-candidate-20260725', 0),
        )->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath(
                'data.errors./raw_html.0',
                'Managed content cannot choose markup, code, classes, or component paths.',
            )
            ->assertJsonPath('meta.lock_version', 0);

        $this->contentPost(
            "/api/content/v1/entries/{$created['id']}/validate",
            ['payload' => $this->standardEntryPayload()['payload']],
            $this->idempotencyHeaders(
                'validate-valid-candidate-20260725',
                0,
                'request-content-api-valid',
            ),
        )->assertOk()
            ->assertJsonPath('data.valid', true);

        $entry = ContentEntry::query()->findOrFail($created['id']);
        $this->assertSame(1, $entry->revisions()->count());
        $this->assertSame(0, $entry->lock_version);
        $events = $entry->auditEvents()->where('action', 'validate')->orderBy('id')->get();
        $this->assertCount(2, $events);
        $this->assertSame([false, true], $events->pluck('metadata.valid')->all());
        $this->assertSame(
            ['request-content-api-001', 'request-content-api-valid'],
            $events->pluck('request_id')->all(),
        );
        $this->assertTrue($events->every(fn ($event): bool => $event->api_key_id !== null));
    }

    public function test_candidate_validation_rejects_an_identity_owned_by_another_entry(): void
    {
        $this->authenticateContentApi([ApiKeyScope::ContentDraft]);
        $first = $this->contentPost(
            '/api/content/v1/entries',
            $this->standardEntryPayload('first-page'),
            $this->idempotencyHeaders('create-first-candidate-owner'),
        )->assertCreated()->json('data');
        $second = $this->contentPost(
            '/api/content/v1/entries',
            $this->standardEntryPayload('second-page'),
            $this->idempotencyHeaders('create-second-candidate-owner'),
        )->assertCreated()->json('data');
        $candidate = $this->standardEntryPayload('first-page');

        $this->contentPost(
            "/api/content/v1/entries/{$second['id']}/validate",
            [
                'identity' => $candidate['identity'],
                'payload' => $candidate['payload'],
            ],
            $this->idempotencyHeaders('validate-conflicting-candidate', 0),
        )->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath(
                'data.errors./identity/slug.0',
                'This public path is already owned by another content entry.',
            );

        $this->assertSame('first-page', $first['slug']);
        $this->assertDatabaseCount('content_entries', 2);
        $this->assertDatabaseCount('content_revisions', 2);
    }

    public function test_revision_writes_require_if_match_and_reject_stale_versions(): void
    {
        $this->authenticateContentApi([
            ApiKeyScope::ContentRead,
            ApiKeyScope::ContentDraft,
        ]);
        $entryId = $this->contentPost(
            '/api/content/v1/entries',
            $this->standardEntryPayload(),
            $this->idempotencyHeaders('create-revision-target'),
        )->json('data.id');
        $changed = $this->standardEntryPayload();
        $changed['payload']['blocks'][0]['props']['answer'][0]['text'] = 'The updated answer.';

        $this->contentPost(
            "/api/content/v1/entries/{$entryId}/revisions",
            ['payload' => $changed['payload'], 'summary' => 'Updated answer'],
            ['Idempotency-Key' => 'missing-if-match-20260725'],
        )->assertUnprocessable()
            ->assertJsonPath('code', 'if_match_required');

        $this->contentPost(
            "/api/content/v1/entries/{$entryId}/revisions",
            ['payload' => $changed['payload'], 'summary' => 'Updated answer'],
            $this->idempotencyHeaders('save-revision-20260725', 0),
        )->assertCreated()
            ->assertHeader('ETag', '"1"')
            ->assertJsonPath('data.revision_number', 2);

        $this->contentPost(
            "/api/content/v1/entries/{$entryId}/revisions",
            ['payload' => $changed['payload'], 'summary' => 'Stale update'],
            $this->idempotencyHeaders('stale-revision-20260725', 0),
        )->assertConflict()
            ->assertJsonPath('code', 'content_conflict')
            ->assertJsonPath('current_lock_version', 1);
    }

    public function test_a_revision_from_another_entry_is_not_disclosed(): void
    {
        $this->authenticateContentApi([
            ApiKeyScope::ContentRead,
            ApiKeyScope::ContentDraft,
        ]);
        $first = $this->contentPost(
            '/api/content/v1/entries',
            $this->standardEntryPayload('first'),
            $this->idempotencyHeaders('create-first-entry'),
        )->json('data');
        $second = $this->contentPost(
            '/api/content/v1/entries',
            $this->standardEntryPayload('second'),
            $this->idempotencyHeaders('create-second-entry'),
        )->json('data');

        $this->contentGet(
            "/api/content/v1/entries/{$first['id']}/revisions/{$second['latest_revision']['id']}",
        )->assertNotFound()
            ->assertJsonPath('code', 'not_found');
    }
}
