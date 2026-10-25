<?php

namespace Tests\Feature;

use App\Content\Enums\ApiKeyScope;
use App\Models\ApiIdempotencyRecord;
use App\Models\User;
use App\Services\ApiIdempotency;
use App\Services\ApiKeyManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ApiIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/api/test-idempotency', function (Request $request) {
            return app(ApiIdempotency::class)->execute(
                $request,
                $request->attributes->get('api_key'),
                'test.write',
                fn () => response()->json([
                    'created' => true,
                    'nonce' => (string) Str::uuid(),
                ], 201)->header('ETag', '"7"'),
            );
        })->middleware([
            'api-key.auth',
            'api-key.scope:content:draft',
        ]);

        Route::post('/api/test-idempotency-other', function (Request $request) {
            return app(ApiIdempotency::class)->execute(
                $request,
                $request->attributes->get('api_key'),
                'test.other-write',
                fn () => response()->json(['created' => 'other'], 201),
            );
        })->middleware([
            'api-key.auth',
            'api-key.scope:content:draft',
        ]);

        Route::post('/api/test-idempotency-large', function (Request $request) {
            return app(ApiIdempotency::class)->execute(
                $request,
                $request->attributes->get('api_key'),
                'test.large-write',
                function () {
                    User::factory()->create();

                    return response()->json(['payload' => str_repeat('x', 1_048_576)]);
                },
            );
        })->middleware([
            'api-key.auth',
            'api-key.scope:content:draft',
        ]);

        Route::post('/api/test-idempotency-secret', function (Request $request) {
            return app(ApiIdempotency::class)->execute(
                $request,
                $request->attributes->get('api_key'),
                'content.entry.42.preview',
                fn () => response()->json([
                    'data' => [
                        'url' => 'https://example.test/_preview/secret-preview-token',
                    ],
                ], 201),
            );
        })->middleware([
            'api-key.auth',
            'api-key.scope:content:draft',
        ]);
    }

    public function test_write_requires_a_valid_idempotency_key(): void
    {
        [$owner, $plaintext] = $this->key();

        $this->withBasicAuth($owner->email, $plaintext)
            ->postJson('/api/test-idempotency', ['title' => 'Draft'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'idempotency_key_required');
    }

    public function test_same_key_and_request_replays_bounded_saved_response(): void
    {
        [$owner, $plaintext] = $this->key();
        $headers = ['Idempotency-Key' => 'draft-2026-07-25-001'];

        $first = $this->withBasicAuth($owner->email, $plaintext)
            ->postJson('/api/test-idempotency', ['title' => 'Draft'], $headers)
            ->assertCreated();
        $second = $this->withBasicAuth($owner->email, $plaintext)
            ->postJson('/api/test-idempotency', ['title' => 'Draft'], $headers)
            ->assertCreated()
            ->assertHeader('ETag', '"7"')
            ->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame($first->json(), $second->json());
        $this->assertDatabaseCount('api_idempotency_records', 1);
        $record = ApiIdempotencyRecord::query()->sole();
        $this->assertArrayNotHasKey('idempotency_key_hash', $record->toArray());
        $this->assertArrayNotHasKey('request_hash', $record->toArray());
        $this->assertDatabaseMissing('api_idempotency_records', [
            'idempotency_key_hash' => 'draft-2026-07-25-001',
        ]);
    }

    public function test_key_is_claimed_before_the_write_callback_executes(): void
    {
        [$owner] = $this->key();
        $issued = $owner->apiKeys()->sole();
        $request = Request::create(
            '/api/test-idempotency',
            'POST',
            ['title' => 'Draft'],
            server: ['HTTP_IDEMPOTENCY_KEY' => 'atomic-claim-001'],
        );

        $response = app(ApiIdempotency::class)->execute(
            $request,
            $issued,
            'test.atomic-write',
            function () {
                $claim = ApiIdempotencyRecord::query()->sole();
                $this->assertSame(102, $claim->response_status);
                $this->assertSame('idempotency_in_progress', $claim->response_body['code']);

                return response()->json(['created' => true], 201);
            },
        );

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(201, ApiIdempotencyRecord::query()->sole()->response_status);
    }

    public function test_reusing_a_key_for_another_operation_returns_conflict(): void
    {
        [$owner, $plaintext] = $this->key();
        $headers = ['Idempotency-Key' => 'cross-operation-001'];

        $this->withBasicAuth($owner->email, $plaintext)
            ->postJson('/api/test-idempotency', ['title' => 'Draft'], $headers)
            ->assertCreated();
        $this->withBasicAuth($owner->email, $plaintext)
            ->postJson('/api/test-idempotency-other', ['title' => 'Draft'], $headers)
            ->assertConflict()
            ->assertJsonPath('code', 'idempotency_conflict');

        $this->assertDatabaseCount('api_idempotency_records', 1);
    }

    public function test_reusing_a_key_with_changed_request_returns_conflict_without_execution(): void
    {
        [$owner, $plaintext] = $this->key();
        $headers = ['Idempotency-Key' => 'draft-2026-07-25-002'];

        $this->withBasicAuth($owner->email, $plaintext)
            ->postJson('/api/test-idempotency', ['title' => 'First'], $headers)
            ->assertCreated();
        $this->withBasicAuth($owner->email, $plaintext)
            ->postJson('/api/test-idempotency', ['title' => 'Changed'], $headers)
            ->assertConflict()
            ->assertJsonPath('code', 'idempotency_conflict');

        $this->assertDatabaseCount('api_idempotency_records', 1);
    }

    public function test_oversized_response_rolls_back_the_write_and_stores_no_replay_record(): void
    {
        [$owner, $plaintext] = $this->key();
        $headers = ['Idempotency-Key' => 'large-write-001'];

        $this->withBasicAuth($owner->email, $plaintext)
            ->postJson('/api/test-idempotency-large', [], $headers)
            ->assertStatus(500)
            ->assertJsonPath('code', 'idempotency_response_too_large');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('api_idempotency_records', 0);

        $this->withBasicAuth($owner->email, $plaintext)
            ->postJson('/api/test-idempotency-large', [], $headers)
            ->assertStatus(500);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('api_idempotency_records', 0);
    }

    public function test_preview_replay_body_is_encrypted_at_rest_and_still_replays(): void
    {
        [$owner, $plaintext] = $this->key();
        $headers = ['Idempotency-Key' => 'preview-secret-001'];

        $first = $this->withBasicAuth($owner->email, $plaintext)
            ->postJson('/api/test-idempotency-secret', [], $headers)
            ->assertCreated();
        $second = $this->withBasicAuth($owner->email, $plaintext)
            ->postJson('/api/test-idempotency-secret', [], $headers)
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true');
        $stored = (array) DB::table('api_idempotency_records')->sole();

        $this->assertSame($first->json(), $second->json());
        $this->assertStringNotContainsString(
            'secret-preview-token',
            json_encode($stored, JSON_THROW_ON_ERROR),
        );
        $this->assertNotEmpty($stored['response_body_ciphertext'] ?? null);
    }

    public function test_external_writes_run_outside_the_ledger_transaction_and_survive_later_failures(): void
    {
        [$owner] = $this->key();
        $issued = $owner->apiKeys()->sole();
        $request = Request::create(
            '/api/media/import',
            'POST',
            ['items' => [['source_url' => 'https://cdn.example.com/photo.jpg']]],
            server: ['HTTP_IDEMPOTENCY_KEY' => 'external-write-001'],
        );
        $transactionLevel = DB::transactionLevel();

        try {
            app(ApiIdempotency::class)->executeExternal(
                $request,
                $issued,
                'media.import',
                function () use ($transactionLevel): never {
                    $this->assertSame($transactionLevel, DB::transactionLevel());
                    User::factory()->create();

                    throw new RuntimeException('later import failed');
                },
            );
            $this->fail('The external callback exception must remain visible.');
        } catch (RuntimeException $exception) {
            $this->assertSame('later import failed', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('api_idempotency_records', 0);
    }

    public function test_external_write_claim_remains_in_progress_when_ledger_finalization_fails(): void
    {
        [$owner] = $this->key();
        $issued = $owner->apiKeys()->sole();
        $request = Request::create(
            '/api/media/import',
            'POST',
            ['items' => [['source_url' => 'https://cdn.example.com/photo.jpg']]],
            server: ['HTTP_IDEMPOTENCY_KEY' => 'external-finalize-failure-001'],
        );

        DB::unprepared(
            "CREATE TRIGGER fail_idempotency_finalize
            BEFORE UPDATE ON api_idempotency_records
            BEGIN
                SELECT RAISE(FAIL, 'ledger finalization failed');
            END",
        );

        try {
            app(ApiIdempotency::class)->executeExternal(
                $request,
                $issued,
                'media.import',
                function () {
                    User::factory()->create();

                    return response()->json(['created' => true], 201);
                },
            );
            $this->fail('Ledger finalization failures must remain visible.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString(
                'ledger finalization failed',
                $exception->getMessage(),
            );
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS fail_idempotency_finalize');
        }

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseHas('api_idempotency_records', [
            'response_status' => 102,
        ]);
    }

    public function test_oversized_external_success_is_returned_and_compacted_for_replay(): void
    {
        [$owner] = $this->key();
        $issued = $owner->apiKeys()->sole();
        $request = Request::create(
            '/api/media/import',
            'POST',
            ['items' => [['source_url' => 'https://cdn.example.com/photo.jpg']]],
            server: ['HTTP_IDEMPOTENCY_KEY' => 'external-large-success-001'],
        );
        $callbackRuns = 0;
        $callback = function () use (&$callbackRuns) {
            $callbackRuns++;
            User::factory()->create();

            return response()->json([
                'created' => true,
                'payload' => str_repeat('x', 1_048_576),
            ], 201);
        };

        $first = app(ApiIdempotency::class)->executeExternal(
            $request,
            $issued,
            'media.import',
            $callback,
        );
        $replay = app(ApiIdempotency::class)->executeExternal(
            $request,
            $issued,
            'media.import',
            $callback,
        );

        $this->assertSame(201, $first->getStatusCode());
        $this->assertStringContainsString('"created":true', (string) $first->getContent());
        $this->assertSame('false', $first->headers->get('Idempotency-Replayed'));
        $this->assertSame(201, $replay->getStatusCode());
        $this->assertSame('true', $replay->headers->get('Idempotency-Replayed'));
        $this->assertSame(
            'idempotency_replay_compacted',
            $replay->getData(true)['code'] ?? null,
        );
        $this->assertSame(1, $callbackRuns);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseHas('api_idempotency_records', [
            'response_status' => 201,
        ]);
    }

    /** @return array{User, string} */
    private function key(): array
    {
        $owner = User::factory()->create();
        $issued = app(ApiKeyManager::class)->create(
            $owner,
            'Hermes writer',
            [ApiKeyScope::ContentDraft],
        );

        return [$owner, $issued->plaintext];
    }
}
