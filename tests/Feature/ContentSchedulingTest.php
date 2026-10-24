<?php

namespace Tests\Feature;

use App\Content\ContentLifecycle;
use App\Content\Enums\ApiKeyScope;
use App\Content\Enums\ContentKind;
use App\Content\Enums\ContentSource;
use App\Content\Enums\ContentStatus;
use App\Content\Enums\PageTemplate;
use App\Content\Exceptions\ContentValidationFailed;
use App\Models\User;
use App\Services\ApiKeyManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContentSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_scheduler_keeps_current_revision_live_until_the_exact_due_revision_publishes(): void
    {
        CarbonImmutable::setTestNow('2026-07-25 10:00:00');
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft(
            $this->identity(),
            $this->payload('Current homepage'),
            $user,
        );
        $current = $entry->revisions()->sole();
        $entry = $lifecycle->publish($entry, $current, 0, $user);
        $scheduled = $lifecycle->saveRevision(
            $entry,
            $this->payload('Scheduled homepage'),
            1,
            $user,
            ContentSource::Admin,
        );
        $entry = $lifecycle->schedule(
            $entry->fresh(),
            $scheduled,
            CarbonImmutable::now()->addHour(),
            2,
            $user,
        );

        $this->assertSame(ContentStatus::Scheduled, $entry->status);
        $this->assertSame($current->id, $entry->published_revision_id);
        $this->assertSame($scheduled->id, $entry->scheduled_revision_id);
        $this->assertSame(ContentKind::Page->value, $entry->scheduled_kind);
        $this->assertSame('/', $entry->scheduled_slug);
        $this->get('/')->assertSee('Current homepage')->assertDontSee('Scheduled homepage');
        $this->artisan('content:publish-scheduled')->assertSuccessful();
        $this->assertSame($current->id, $entry->fresh()->published_revision_id);

        CarbonImmutable::setTestNow('2026-07-25 11:01:00');
        $this->artisan('content:publish-scheduled')
            ->expectsOutputToContain('Published 1 scheduled content entry.')
            ->assertSuccessful();

        $entry->refresh();
        $this->assertSame(ContentStatus::Published, $entry->status);
        $this->assertSame($scheduled->id, $entry->published_revision_id);
        $this->assertNull($entry->scheduled_revision_id);
        $this->assertNull($entry->scheduled_kind);
        $this->assertNull($entry->scheduled_slug);
        $this->assertNull($entry->scheduled_for);
        $this->get('/')->assertSee('Scheduled homepage')->assertDontSee('Current homepage');
    }

    public function test_cancelling_schedule_preserves_current_public_revision(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft(
            $this->identity(),
            $this->payload('Current homepage'),
            $user,
        );
        $current = $entry->revisions()->sole();
        $entry = $lifecycle->publish($entry, $current, 0, $user);
        $scheduled = $lifecycle->saveRevision(
            $entry,
            $this->payload('Cancelled homepage'),
            1,
            $user,
            ContentSource::Admin,
        );
        $entry = $lifecycle->schedule(
            $entry->fresh(),
            $scheduled,
            CarbonImmutable::now()->addDay(),
            2,
            $user,
        );

        $cancelled = $lifecycle->cancelSchedule($entry, 3, $user);

        $this->assertSame(ContentStatus::Published, $cancelled->status);
        $this->assertSame($current->id, $cancelled->published_revision_id);
        $this->assertNull($cancelled->scheduled_revision_id);
        $this->assertNull($cancelled->scheduled_kind);
        $this->assertNull($cancelled->scheduled_slug);
        $this->assertDatabaseHas('content_audit_events', [
            'content_entry_id' => $entry->id,
            'action' => 'cancel_schedule',
        ]);
    }

    public function test_due_publication_is_attributed_to_the_scheduling_principal_and_key(): void
    {
        CarbonImmutable::setTestNow('2026-07-25 10:00:00');
        $scheduler = User::factory()->create();
        $laterEditor = User::factory()->create();
        $issued = app(ApiKeyManager::class)->create(
            $scheduler,
            'Scheduler',
            [ApiKeyScope::ContentPublish],
        );
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft(
            $this->identity(),
            $this->payload('Current homepage'),
            $scheduler,
        );
        $current = $entry->revisions()->sole();
        $entry = $lifecycle->publish($entry, $current, 0, $scheduler);
        $scheduled = $lifecycle->saveRevision(
            $entry,
            $this->payload('Scheduled homepage'),
            1,
            $scheduler,
            ContentSource::Api,
            $issued->key,
        );
        $entry = $lifecycle->schedule(
            $entry->fresh(),
            $scheduled,
            CarbonImmutable::now()->addHour(),
            2,
            $scheduler,
            $issued->key,
        );

        $lifecycle->saveRevision(
            $entry,
            $this->payload('Later unrelated draft'),
            3,
            $laterEditor,
            ContentSource::Admin,
        );

        CarbonImmutable::setTestNow('2026-07-25 11:01:00');
        $this->artisan('content:publish-scheduled')->assertSuccessful();

        $this->assertDatabaseHas('content_audit_events', [
            'content_entry_id' => $entry->id,
            'content_revision_id' => $scheduled->id,
            'user_id' => $scheduler->id,
            'api_key_id' => $issued->key->id,
            'action' => 'publish',
        ]);
    }

    public function test_active_schedule_reserves_its_revision_identity(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $first = $lifecycle->createDraft(
            [...$this->identity(), 'slug' => 'first-page'],
            $this->payload('First page'),
            $user,
        );
        $second = $lifecycle->createDraft(
            [...$this->identity(), 'slug' => 'second-page'],
            $this->payload('Second page'),
            $user,
        );
        $first = $lifecycle->publish($first, $first->revisions()->sole(), 0, $user);
        $second = $lifecycle->publish($second, $second->revisions()->sole(), 0, $user);
        $firstTarget = $lifecycle->saveRevision(
            $first,
            $this->payload('First scheduled target'),
            1,
            $user,
            ContentSource::Admin,
            identity: [...$this->identity(), 'slug' => 'shared-future-path'],
        );
        $secondTarget = $lifecycle->saveRevision(
            $second,
            $this->payload('Second scheduled target'),
            1,
            $user,
            ContentSource::Admin,
            identity: [...$this->identity(), 'slug' => 'shared-future-path'],
        );
        $lifecycle->schedule(
            $first->fresh(),
            $firstTarget,
            CarbonImmutable::now()->addDay(),
            2,
            $user,
        );

        try {
            $lifecycle->schedule(
                $second->fresh(),
                $secondTarget,
                CarbonImmutable::now()->addDays(2),
                2,
                $user,
            );
            $this->fail('An active schedule must reserve its future public identity.');
        } catch (ContentValidationFailed $failure) {
            $this->assertArrayHasKey('/identity/slug', $failure->result->errors());
        }

        $this->assertNull($second->fresh()->scheduled_revision_id);
    }

    public function test_unpublished_identity_change_moves_the_current_claim_immediately(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $first = $lifecycle->createDraft(
            [...$this->identity(), 'slug' => 'first-page'],
            $this->payload('First page'),
            $user,
        );
        $lifecycle->saveRevision(
            $first,
            $this->payload('Moved draft'),
            0,
            $user,
            ContentSource::Admin,
            identity: [...$this->identity(), 'slug' => 'shared-future-path'],
        );

        $this->assertDatabaseMissing('content_identity_claims', [
            'content_entry_id' => $first->id,
            'claim_type' => 'current',
            'slug' => 'first-page',
        ]);
        $this->assertDatabaseHas('content_identity_claims', [
            'content_entry_id' => $first->id,
            'claim_type' => 'current',
            'slug' => 'shared-future-path',
        ]);

        try {
            $lifecycle->createDraft(
                [...$this->identity(), 'slug' => 'shared-future-path'],
                $this->payload('Competing draft'),
                $user,
            );
            $this->fail('The moved draft identity must remain exclusively claimed.');
        } catch (ContentValidationFailed $failure) {
            $this->assertArrayHasKey('/identity/slug', $failure->result->errors());
        }
    }

    public function test_unpublished_identity_moves_preserve_an_active_scheduled_claim(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft(
            [...$this->identity(), 'slug' => 'draft-a'],
            $this->payload('Draft A'),
            $user,
        );
        $scheduled = $lifecycle->saveRevision(
            $entry,
            $this->payload('Scheduled B'),
            0,
            $user,
            ContentSource::Admin,
            identity: [...$this->identity(), 'slug' => 'draft-b'],
        );
        $entry = $lifecycle->schedule(
            $entry->fresh(),
            $scheduled,
            CarbonImmutable::now()->addDay(),
            1,
            $user,
        );

        $lifecycle->saveRevision(
            $entry,
            $this->payload('Later draft C'),
            2,
            $user,
            ContentSource::Admin,
            identity: [...$this->identity(), 'slug' => 'draft-c'],
        );

        $this->assertDatabaseHas('content_identity_claims', [
            'content_entry_id' => $entry->id,
            'claim_type' => 'current',
            'slug' => 'draft-c',
        ]);
        $this->assertDatabaseHas('content_identity_claims', [
            'content_entry_id' => $entry->id,
            'claim_type' => 'scheduled',
            'slug' => 'draft-b',
        ]);

        try {
            $lifecycle->createDraft(
                [...$this->identity(), 'slug' => 'draft-b'],
                $this->payload('Competing draft'),
                $user,
            );
            $this->fail('The still-active scheduled identity must remain claimed.');
        } catch (ContentValidationFailed $failure) {
            $this->assertArrayHasKey('/identity/slug', $failure->result->errors());
        }
    }

    public function test_unpublished_same_identity_save_keeps_one_current_claim(): void
    {
        CarbonImmutable::setTestNow('2026-07-26 06:20:00');
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft(
            [...$this->identity(), 'slug' => 'same-draft'],
            $this->payload('Initial draft'),
            $user,
        );

        $lifecycle->saveRevision(
            $entry,
            $this->payload('Updated draft'),
            0,
            $user,
            ContentSource::Admin,
            identity: [...$this->identity(), 'slug' => 'same-draft'],
        );

        $this->assertSame(
            1,
            DB::table('content_identity_claims')
                ->where('content_entry_id', $entry->id)
                ->where('claim_type', 'current')
                ->count(),
        );
    }

    public function test_current_and_scheduled_identities_share_one_database_claim_namespace(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $first = $lifecycle->createDraft(
            [...$this->identity(), 'slug' => 'first-page'],
            $this->payload('First page'),
            $user,
        );
        $second = $lifecycle->createDraft(
            [...$this->identity(), 'slug' => 'second-page'],
            $this->payload('Second page'),
            $user,
        );
        $first = $lifecycle->publish(
            $first,
            $first->revisions()->sole(),
            0,
            $user,
        );
        $target = $lifecycle->saveRevision(
            $first,
            $this->payload('Scheduled target'),
            1,
            $user,
            ContentSource::Admin,
            identity: [...$this->identity(), 'slug' => 'shared-claim'],
        );
        $lifecycle->schedule(
            $first->fresh(),
            $target,
            CarbonImmutable::now()->addDay(),
            2,
            $user,
        );

        $this->assertDatabaseHas('content_identity_claims', [
            'content_entry_id' => $first->id,
            'claim_type' => 'scheduled',
            'kind' => ContentKind::Page->value,
            'slug' => 'shared-claim',
        ]);

        $this->expectException(QueryException::class);
        DB::table('content_identity_claims')->insert([
            'content_entry_id' => $second->id,
            'claim_type' => 'current',
            'kind' => ContentKind::Page->value,
            'slug' => 'shared-claim',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_constraint_serializes_competing_future_identity_reservations(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $first = $lifecycle->createDraft(
            [...$this->identity(), 'slug' => 'first-page'],
            $this->payload('First page'),
            $user,
        );
        $second = $lifecycle->createDraft(
            [...$this->identity(), 'slug' => 'second-page'],
            $this->payload('Second page'),
            $user,
        );
        $first->forceFill([
            'scheduled_kind' => ContentKind::Page->value,
            'scheduled_slug' => 'shared-future-path',
        ])->save();

        $this->expectException(QueryException::class);

        $second->forceFill([
            'scheduled_kind' => ContentKind::Page->value,
            'scheduled_slug' => 'shared-future-path',
        ])->save();
    }

    public function test_import_rejects_an_identity_reserved_by_an_active_schedule(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $scheduledEntry = $lifecycle->createDraft(
            [...$this->identity(), 'slug' => 'scheduled-owner'],
            $this->payload('Scheduled owner'),
            $user,
        );
        $scheduledEntry = $lifecycle->publish(
            $scheduledEntry,
            $scheduledEntry->revisions()->sole(),
            0,
            $user,
        );
        $scheduledRevision = $lifecycle->saveRevision(
            $scheduledEntry,
            $this->payload('Reserved legacy import'),
            1,
            $user,
            ContentSource::Admin,
            identity: [...$this->identity(), 'slug' => 'reserved-import'],
        );
        $lifecycle->schedule(
            $scheduledEntry->fresh(),
            $scheduledRevision,
            CarbonImmutable::now()->addDay(),
            2,
            $user,
        );
        $this->assertDatabaseHas('content_entries', [
            'id' => $scheduledEntry->id,
            'scheduled_kind' => ContentKind::Page->value,
            'scheduled_slug' => 'reserved-import',
        ]);

        try {
            $lifecycle->importDraft(
                [...$this->identity(), 'slug' => 'reserved-import'],
                $this->payload('Imported legacy page'),
                $user,
                'legacy:reserved-import',
            );
            $this->fail('Import must not claim an identity reserved for scheduled publication.');
        } catch (ContentValidationFailed $failure) {
            $this->assertArrayHasKey('/identity/slug', $failure->result->errors());
        }

        $this->assertDatabaseMissing('content_entries', [
            'slug' => 'reserved-import',
        ]);
    }

    /**
     * @return array<string, ContentKind|PageTemplate|string>
     */
    private function identity(): array
    {
        return [
            'kind' => ContentKind::Page,
            'slug' => '/',
            'title' => 'Homepage',
            'template' => PageTemplate::Standard,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $heading): array
    {
        return [
            'title' => $heading,
            'blocks' => [[
                'type' => 'hero/split',
                'props' => ['heading' => $heading],
            ]],
        ];
    }
}
