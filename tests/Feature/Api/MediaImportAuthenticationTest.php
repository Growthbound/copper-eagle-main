<?php

namespace Tests\Feature\Api;

use App\Content\Enums\ApiKeyScope;
use App\Models\User;
use App\Services\ApiKeyManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MediaImportAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_import_route_has_a_stable_name_uri_and_protection(): void
    {
        $route = Route::getRoutes()->getByName('api.media.import');

        $this->assertNotNull($route);
        $this->assertSame('api/media/import', $route->uri());
        $this->assertContains('POST', $route->methods());
        $this->assertContains('api-key.auth', $route->gatherMiddleware());
        $this->assertContains('api-key.resolve', $route->gatherMiddleware());
        $this->assertContains('api-key.scope:media:import', $route->gatherMiddleware());
        $this->assertContains('api-key.idempotency', $route->gatherMiddleware());
        $this->assertContains('api-peer-throttle:media-import-api,60', $route->gatherMiddleware());
        $this->assertContains('throttle:media-import-api', $route->gatherMiddleware());
        $this->assertContains('content.request-id', $route->gatherMiddleware());
        $this->assertLessThan(
            array_search('api-key.resolve', $route->gatherMiddleware(), true),
            array_search('content.request-id', $route->gatherMiddleware(), true),
        );
        $this->assertLessThan(
            array_search('api-key.resolve', $route->gatherMiddleware(), true),
            array_search('api-peer-throttle:media-import-api,60', $route->gatherMiddleware(), true),
        );
        $this->assertLessThan(
            array_search('api-key.auth', $route->gatherMiddleware(), true),
            array_search('throttle:media-import-api', $route->gatherMiddleware(), true),
        );
        $this->assertLessThan(
            array_search('throttle:media-import-api', $route->gatherMiddleware(), true),
            array_search('api-key.resolve', $route->gatherMiddleware(), true),
        );
    }

    public function test_media_import_limits_have_bounded_defaults(): void
    {
        $this->assertSame(25, config('media-import.max_items'));
        $this->assertSame(25 * 1024 * 1024, config('media-import.max_bytes'));
        $this->assertSame(5, config('media-import.connect_timeout'));
        $this->assertSame(30, config('media-import.timeout'));
        $this->assertSame(3, config('media-import.max_redirects'));
    }

    public function test_missing_credentials_are_rejected(): void
    {
        $this->withHeader('X-Request-ID', 'media-import-auth-001')
            ->postJson('/api/media/import', ['items' => []])
            ->assertUnauthorized()
            ->assertHeader('X-Request-ID', 'media-import-auth-001')
            ->assertHeader('WWW-Authenticate', 'Basic realm="Content API"')
            ->assertExactJson([
                'message' => 'Invalid API credentials.',
                'code' => 'unauthenticated',
                'request_id' => 'media-import-auth-001',
            ]);
    }

    public function test_a_malformed_key_is_rejected(): void
    {
        $owner = User::factory()->create();

        $this->withHeader('X-Request-ID', 'media-import-auth-002')
            ->withBasicAuth($owner->email, 'not-a-key')
            ->postJson('/api/media/import', ['items' => []])
            ->assertUnauthorized()
            ->assertHeader('X-Request-ID', 'media-import-auth-002')
            ->assertExactJson([
                'message' => 'Invalid API credentials.',
                'code' => 'unauthenticated',
                'request_id' => 'media-import-auth-002',
            ]);
    }

    public function test_an_unknown_owner_email_is_rejected(): void
    {
        $issued = app(ApiKeyManager::class)->create(User::factory()->create(), 'Test importer');

        $this->withHeader('X-Request-ID', 'media-import-auth-003')
            ->withBasicAuth('unknown@example.com', $issued->plaintext)
            ->postJson('/api/media/import', ['items' => []])
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Invalid API credentials.',
                'code' => 'unauthenticated',
                'request_id' => 'media-import-auth-003',
            ]);

        $this->assertNull($issued->key->refresh()->last_used_at);
    }

    public function test_a_wrong_secret_is_rejected(): void
    {
        $owner = User::factory()->create();
        $issued = app(ApiKeyManager::class)->create($owner, 'Test importer');
        $wrongKey = substr($issued->plaintext, 0, -1)
            .(str_ends_with($issued->plaintext, '0') ? '1' : '0');

        $this->withHeader('X-Request-ID', 'media-import-auth-004')
            ->withBasicAuth($owner->email, $wrongKey)
            ->postJson('/api/media/import', ['items' => []])
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Invalid API credentials.',
                'code' => 'unauthenticated',
                'request_id' => 'media-import-auth-004',
            ]);

        $this->assertNull($issued->key->refresh()->last_used_at);
    }

    public function test_a_revoked_key_is_rejected(): void
    {
        $owner = User::factory()->create();
        $manager = app(ApiKeyManager::class);
        $issued = $manager->create($owner, 'Test importer');
        $manager->revoke($issued->key);

        $this->withHeader('X-Request-ID', 'media-import-auth-005')
            ->withBasicAuth($owner->email, $issued->plaintext)
            ->postJson('/api/media/import', ['items' => []])
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Invalid API credentials.',
                'code' => 'unauthenticated',
                'request_id' => 'media-import-auth-005',
            ]);
    }

    public function test_rotation_rejects_the_old_key_and_accepts_the_replacement(): void
    {
        $owner = User::factory()->create();
        $manager = app(ApiKeyManager::class);
        $old = $manager->create($owner, 'Test importer');
        $replacement = $manager->rotate($old->key);

        $this->withHeader('X-Request-ID', 'media-import-auth-006')
            ->withBasicAuth($owner->email, $old->plaintext)
            ->postJson('/api/media/import', ['items' => []])
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Invalid API credentials.',
                'code' => 'unauthenticated',
                'request_id' => 'media-import-auth-006',
            ]);

        $this->withBasicAuth($owner->email, $replacement->plaintext)
            ->withHeader('Idempotency-Key', 'rotation-replacement-20260726')
            ->postJson('/api/media/import', ['items' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_correct_basic_credentials_reach_the_import_route(): void
    {
        $owner = User::factory()->create();
        $issued = app(ApiKeyManager::class)->create($owner, 'Test importer');

        $this->withHeader('X-Request-ID', 'media-import-validation-001')
            ->withBasicAuth($owner->email, $issued->plaintext)
            ->withHeader('Idempotency-Key', 'correct-credentials-20260726')
            ->postJson('/api/media/import', ['items' => []])
            ->assertUnprocessable()
            ->assertHeader('X-Request-ID', 'media-import-validation-001')
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('request_id', 'media-import-validation-001')
            ->assertJsonValidationErrors(['items']);

        $this->assertNotNull($issued->key->refresh()->last_used_at);
    }

    public function test_authenticated_import_requires_an_idempotency_key(): void
    {
        $owner = User::factory()->create();
        $issued = app(ApiKeyManager::class)->create($owner, 'Test importer');

        $this->withBasicAuth($owner->email, $issued->plaintext)
            ->postJson('/api/media/import', ['items' => [[
                'source_url' => 'https://cdn.example.com/photo.jpg',
            ]]])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'idempotency_key_required');
    }

    public function test_key_without_media_import_scope_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $issued = app(ApiKeyManager::class)->create(
            $owner,
            'Content only',
            [ApiKeyScope::ContentRead],
        );

        $this->withBasicAuth($owner->email, $issued->plaintext)
            ->postJson('/api/media/import', ['items' => []])
            ->assertForbidden()
            ->assertJsonPath('code', 'missing_scope');
    }
}
