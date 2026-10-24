<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContentMigrationUpgradeTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = DB::getDefaultConnection();
        config()->set('database.connections.content_migration_upgrade', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('content_migration_upgrade');
        DB::setDefaultConnection('content_migration_upgrade');
    }

    protected function tearDown(): void
    {
        DB::disconnect('content_migration_upgrade');
        DB::setDefaultConnection($this->originalConnection);
        DB::purge('content_migration_upgrade');

        parent::tearDown();
    }

    public function test_encrypted_replay_columns_use_large_text_on_every_schema_path(): void
    {
        foreach ([
            '2026_07_25_000013_harden_idempotency_replays.php',
            '2026_07_25_000014_backfill_content_hardening_upgrades.php',
            '2026_07_25_000015_serialize_content_invariants.php',
        ] as $migration) {
            $source = file_get_contents(database_path('migrations/'.$migration));

            $this->assertIsString($source);
            $this->assertStringContainsString(
                "longText('response_body_ciphertext')",
                $source,
                $migration,
            );
        }
    }

    public function test_content_invariant_upgrade_backfills_one_shared_identity_claim_namespace(): void
    {
        Schema::create('content_entries', function ($table): void {
            $table->id();
            $table->string('kind', 32);
            $table->string('slug');
            $table->string('scheduled_kind', 32)->nullable();
            $table->string('scheduled_slug')->nullable();
        });
        DB::table('content_entries')->insert([
            [
                'id' => 1,
                'kind' => 'page',
                'slug' => 'current-one',
                'scheduled_kind' => 'page',
                'scheduled_slug' => 'future-one',
            ],
            [
                'id' => 2,
                'kind' => 'page',
                'slug' => 'current-two',
                'scheduled_kind' => null,
                'scheduled_slug' => null,
            ],
        ]);

        $this->migration('2026_07_25_000015_serialize_content_invariants.php')->up();

        $this->assertDatabaseHas('content_identity_claims', [
            'content_entry_id' => 1,
            'claim_type' => 'current',
            'slug' => 'current-one',
        ]);
        $this->assertDatabaseHas('content_identity_claims', [
            'content_entry_id' => 1,
            'claim_type' => 'scheduled',
            'slug' => 'future-one',
        ]);
        $this->assertDatabaseHas('content_identity_claims', [
            'content_entry_id' => 2,
            'claim_type' => 'current',
            'slug' => 'current-two',
        ]);
        $this->assertDatabaseHas('content_operation_locks', ['name' => 'redirect-graph']);

        $this->expectException(QueryException::class);
        DB::table('content_identity_claims')->insert([
            'content_entry_id' => 2,
            'claim_type' => 'scheduled',
            'kind' => 'page',
            'slug' => 'future-one',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_idempotency_upgrade_reconciles_cross_operation_duplicate_keys(): void
    {
        Schema::create('api_keys', function ($table): void {
            $table->id();
        });
        Schema::create('api_idempotency_records', function ($table): void {
            $table->id();
            $table->foreignId('api_key_id')->constrained()->cascadeOnDelete();
            $table->string('operation', 120);
            $table->char('idempotency_key_hash', 64);
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('response_status');
            $table->json('response_body');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->unique(
                ['api_key_id', 'operation', 'idempotency_key_hash'],
                'api_idempotency_key_operation_unique',
            );
        });

        DB::table('api_keys')->insert(['id' => 1]);
        DB::table('api_idempotency_records')->insert([
            [
                'api_key_id' => 1,
                'operation' => 'content.entry.1.preview',
                'idempotency_key_hash' => str_repeat('a', 64),
                'request_hash' => str_repeat('b', 64),
                'response_status' => 201,
                'response_body' => json_encode(['first' => true], JSON_THROW_ON_ERROR),
                'expires_at' => now()->addHour(),
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
            [
                'api_key_id' => 1,
                'operation' => 'content.entry.1.publish',
                'idempotency_key_hash' => str_repeat('a', 64),
                'request_hash' => str_repeat('c', 64),
                'response_status' => 200,
                'response_body' => json_encode(['second' => true], JSON_THROW_ON_ERROR),
                'expires_at' => now()->addHour(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->migration('2026_07_25_000013_harden_idempotency_replays.php')->up();

        $this->assertSame(1, DB::table('api_idempotency_records')->count());
        $this->assertSame(
            'content.entry.1.preview',
            DB::table('api_idempotency_records')->value('operation'),
        );

        $this->expectException(QueryException::class);
        DB::table('api_idempotency_records')->insert([
            'api_key_id' => 1,
            'operation' => 'content.entry.1.rollback',
            'idempotency_key_hash' => str_repeat('a', 64),
            'request_hash' => str_repeat('d', 64),
            'response_status' => 200,
            'response_body' => json_encode(['third' => true], JSON_THROW_ON_ERROR),
            'expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_content_lifecycle_upgrade_backfills_revision_identity_snapshots(): void
    {
        Schema::create('users', function ($table): void {
            $table->id();
        });
        Schema::create('api_keys', function ($table): void {
            $table->id();
        });
        Schema::create('content_entries', function ($table): void {
            $table->id();
            $table->string('kind');
            $table->string('slug');
            $table->string('title');
            $table->string('template');
            $table->foreignId('scheduled_revision_id')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->foreignId('updated_by')->nullable();
        });
        Schema::create('content_revisions', function ($table): void {
            $table->id();
            $table->foreignId('content_entry_id');
            $table->unsignedInteger('schema_version')->default(1);
        });
        Schema::create('redirect_rules', function ($table): void {
            $table->id();
        });

        DB::table('users')->insert(['id' => 1]);
        DB::table('content_entries')->insert([
            'id' => 10,
            'kind' => 'page',
            'slug' => 'original-path',
            'title' => 'Original title',
            'template' => 'standard',
            'scheduled_revision_id' => null,
            'scheduled_for' => null,
            'updated_by' => 1,
        ]);
        DB::table('content_revisions')->insert([
            'id' => 20,
            'content_entry_id' => 10,
            'schema_version' => 1,
        ]);

        $this->migration(
            '2026_07_25_000012_harden_content_lifecycle_and_redirect_audits.php',
        )->up();

        $identity = json_decode(
            DB::table('content_revisions')->where('id', 20)->value('identity'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame([
            'kind' => 'page',
            'slug' => 'original-path',
            'title' => 'Original title',
            'template' => 'standard',
        ], $identity);
    }

    public function test_follow_up_upgrade_repairs_schemas_that_already_ran_prior_migrations(): void
    {
        Schema::create('users', function ($table): void {
            $table->id();
        });
        Schema::create('api_keys', function ($table): void {
            $table->id();
        });
        Schema::create('content_entries', function ($table): void {
            $table->id();
            $table->string('kind');
            $table->string('slug');
            $table->string('title');
            $table->string('template');
        });
        Schema::create('content_revisions', function ($table): void {
            $table->id();
            $table->foreignId('content_entry_id');
            $table->unsignedInteger('schema_version')->default(1);
            $table->json('identity')->nullable();
        });
        Schema::create('api_idempotency_records', function ($table): void {
            $table->id();
            $table->foreignId('api_key_id')->constrained()->cascadeOnDelete();
            $table->string('operation', 120);
            $table->char('idempotency_key_hash', 64);
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('response_status');
            $table->json('response_body');
            $table->json('response_headers')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->unique(
                ['api_key_id', 'idempotency_key_hash'],
                'api_idempotency_key_unique',
            );
        });

        DB::table('users')->insert(['id' => 1]);
        DB::table('api_keys')->insert(['id' => 1]);
        DB::table('content_entries')->insert([
            'id' => 10,
            'kind' => 'page',
            'slug' => 'already-migrated-path',
            'title' => 'Already migrated title',
            'template' => 'standard',
        ]);
        DB::table('content_revisions')->insert([
            'id' => 20,
            'content_entry_id' => 10,
            'schema_version' => 1,
            'identity' => null,
        ]);
        DB::table('api_idempotency_records')->insert([
            'api_key_id' => 1,
            'operation' => 'content.entry.10.preview',
            'idempotency_key_hash' => str_repeat('e', 64),
            'request_hash' => str_repeat('f', 64),
            'response_status' => 201,
            'response_body' => json_encode([
                'data' => ['url' => 'https://example.test/_preview/prior-plaintext-token'],
            ], JSON_THROW_ON_ERROR),
            'response_headers' => json_encode([], JSON_THROW_ON_ERROR),
            'expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migration(
            '2026_07_25_000014_backfill_content_hardening_upgrades.php',
        )->up();

        $identity = json_decode(
            DB::table('content_revisions')->where('id', 20)->value('identity'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $stored = (array) DB::table('api_idempotency_records')->sole();

        $this->assertSame('already-migrated-path', $identity['slug'] ?? null);
        $this->assertTrue(Schema::hasColumn(
            'api_idempotency_records',
            'response_body_ciphertext',
        ));
        $this->assertStringNotContainsString(
            'prior-plaintext-token',
            json_encode($stored, JSON_THROW_ON_ERROR),
        );
        $this->assertNotEmpty($stored['response_body_ciphertext'] ?? null);
    }

    private function migration(string $filename): object
    {
        return require database_path('migrations/'.$filename);
    }
}
