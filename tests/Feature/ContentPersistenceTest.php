<?php

namespace Tests\Feature;

use App\Content\Enums\ApiKeyScope;
use App\Content\Enums\ContentKind;
use App\Content\Enums\ContentSource;
use App\Content\Enums\ContentStatus;
use App\Content\Enums\PageTemplate;
use App\Models\ApiIdempotencyRecord;
use App\Models\ApiKey;
use App\Models\ContentAuditEvent;
use App\Models\ContentEntry;
use App\Models\ContentPreviewToken;
use App\Models\ContentRevision;
use App\Models\Media;
use App\Models\MediaUsage;
use App\Models\RedirectRule;
use App\Models\User;
use App\Services\ApiKeyManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ContentPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_entry_keeps_typed_numbered_revision_snapshots(): void
    {
        $user = User::factory()->create();
        $entry = ContentEntry::create([
            'kind' => ContentKind::Page,
            'slug' => 'about',
            'title' => 'About',
            'template' => PageTemplate::Standard,
            'status' => ContentStatus::Draft,
            'lock_version' => 0,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $first = $entry->revisions()->create([
            'revision_number' => 1,
            'schema_version' => 1,
            'payload' => ['title' => 'About', 'blocks' => []],
            'source' => ContentSource::Admin,
            'created_by' => $user->id,
        ]);

        $this->assertSame(1, $first->revision_number);
        $this->assertSame(['title' => 'About', 'blocks' => []], $first->payload);
        $this->assertSame(ContentSource::Admin, $first->source);
        $this->assertSame(ContentKind::Page, $entry->kind);
        $this->assertSame(ContentStatus::Draft, $entry->status);
        $this->assertSame(PageTemplate::Standard, $entry->template);
        $this->assertTrue($entry->is($first->entry));
        $this->assertTrue($user->is($first->user));
    }

    public function test_entry_points_to_exact_published_and_scheduled_revisions(): void
    {
        [$entry, $first, $second] = $this->entryWithTwoRevisions();

        $entry->update([
            'published_revision_id' => $first->id,
            'scheduled_revision_id' => $second->id,
            'scheduled_for' => now()->addHour(),
        ]);

        $this->assertTrue($first->is($entry->fresh()->publishedRevision));
        $this->assertTrue($second->is($entry->fresh()->scheduledRevision));
        $this->assertTrue($entry->scheduled_for->isImmutable());
    }

    public function test_revision_snapshots_cannot_be_changed_or_deleted(): void
    {
        [, $revision] = $this->entryWithTwoRevisions();

        try {
            $revision->update(['summary' => 'Rewritten history']);
            $this->fail('Immutable revisions must reject updates.');
        } catch (LogicException $exception) {
            $this->assertSame('Content revisions are immutable.', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Content revisions are immutable.');

        $revision->delete();
    }

    public function test_support_records_keep_auditable_relationships_and_hide_hashes(): void
    {
        [$entry, $revision] = $this->entryWithTwoRevisions();
        $user = $entry->creator;
        $issued = app(ApiKeyManager::class)->create($user, 'Hermes');
        $media = Media::create([
            'path' => 'media/proof.jpg',
            'original_name' => 'proof.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'width' => 1200,
            'height' => 800,
            'alt' => 'A finished project photo',
        ]);

        $usage = MediaUsage::create([
            'media_id' => $media->id,
            'content_revision_id' => $revision->id,
            'json_pointer' => '/blocks/0/props/image',
            'usage_type' => 'image',
        ]);
        $preview = ContentPreviewToken::create([
            'content_entry_id' => $entry->id,
            'content_revision_id' => $revision->id,
            'token_hash' => hash('sha256', 'preview-secret'),
            'expires_at' => now()->addHour(),
            'created_by' => $user->id,
        ]);
        $audit = ContentAuditEvent::create([
            'content_entry_id' => $entry->id,
            'content_revision_id' => $revision->id,
            'user_id' => $user->id,
            'api_key_id' => $issued->key->id,
            'action' => 'save_revision',
            'request_id' => 'request-123',
            'metadata' => ['summary' => 'Owner-approved edit'],
        ]);

        $this->assertTrue($media->is($usage->media));
        $this->assertTrue($revision->is($usage->revision));
        $this->assertTrue($usage->is($revision->mediaUsages()->sole()));
        $this->assertTrue($revision->is($preview->revision));
        $this->assertTrue($entry->is($preview->entry));
        $this->assertTrue($issued->key->is($audit->apiKey));
        $this->assertSame(['summary' => 'Owner-approved edit'], $audit->metadata);
        $this->assertTrue($preview->expires_at->isImmutable());
        $this->assertArrayNotHasKey('token_hash', $preview->toArray());
    }

    public function test_redirect_and_idempotency_records_cast_and_hide_sensitive_fields(): void
    {
        $user = User::factory()->create();
        $issued = app(ApiKeyManager::class)->create($user, 'Hermes');
        $redirect = RedirectRule::create([
            'source_path' => '/old-service',
            'target' => '/services/example-service',
            'status_code' => 301,
            'enabled' => true,
            'notes' => 'Preserve the old campaign link.',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $record = ApiIdempotencyRecord::create([
            'api_key_id' => $issued->key->id,
            'operation' => 'content.entries.create',
            'idempotency_key_hash' => hash('sha256', 'one-time-key'),
            'request_hash' => hash('sha256', '{"title":"About"}'),
            'response_status' => 201,
            'response_body' => ['data' => ['id' => 12]],
            'expires_at' => now()->addDay(),
        ]);

        $this->assertTrue($redirect->enabled);
        $this->assertSame(301, $redirect->status_code);
        $this->assertTrue($user->is($redirect->creator));
        $this->assertSame(['data' => ['id' => 12]], $record->response_body);
        $this->assertTrue($record->expires_at->isImmutable());
        $this->assertArrayNotHasKey('idempotency_key_hash', $record->toArray());
        $this->assertArrayNotHasKey('request_hash', $record->toArray());
    }

    public function test_audit_events_cannot_be_changed_or_deleted(): void
    {
        [$entry, $revision] = $this->entryWithTwoRevisions();
        $audit = ContentAuditEvent::create([
            'content_entry_id' => $entry->id,
            'content_revision_id' => $revision->id,
            'user_id' => $entry->created_by,
            'action' => 'save_revision',
            'metadata' => ['summary' => 'Original event'],
        ]);

        try {
            $audit->update(['action' => 'publish']);
            $this->fail('Audit history must reject updates.');
        } catch (LogicException $exception) {
            $this->assertSame('Content audit events are immutable.', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Content audit events are immutable.');

        $audit->delete();
    }

    public function test_existing_and_new_api_keys_default_to_media_import_scope(): void
    {
        $issued = app(ApiKeyManager::class)->create(
            User::factory()->create(),
            'Local importer',
        );
        $key = ApiKey::query()->findOrFail($issued->key->id);

        $this->assertSame(['media:import'], $key->scopes);
        $this->assertTrue($key->hasScope(ApiKeyScope::MediaImport));
        $this->assertTrue($key->hasScope('media:import'));
        $this->assertFalse($key->hasScope(ApiKeyScope::ContentPublish));
    }

    /**
     * @return array{ContentEntry, ContentRevision, ContentRevision}
     */
    private function entryWithTwoRevisions(): array
    {
        $user = User::factory()->create();
        $entry = ContentEntry::create([
            'kind' => ContentKind::Page,
            'slug' => 'about',
            'title' => 'About',
            'template' => PageTemplate::Standard,
            'status' => ContentStatus::Draft,
            'lock_version' => 0,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $first = $entry->revisions()->create([
            'revision_number' => 1,
            'schema_version' => 1,
            'payload' => ['title' => 'About', 'blocks' => []],
            'summary' => 'Initial draft',
            'source' => ContentSource::Admin,
            'created_by' => $user->id,
        ]);
        $second = $entry->revisions()->create([
            'revision_number' => 2,
            'schema_version' => 1,
            'payload' => ['title' => 'About Example Co.', 'blocks' => []],
            'summary' => 'Expanded story',
            'source' => ContentSource::Admin,
            'created_by' => $user->id,
        ]);

        return [$entry, $first, $second];
    }
}
