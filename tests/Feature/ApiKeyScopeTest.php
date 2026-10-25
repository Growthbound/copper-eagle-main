<?php

namespace Tests\Feature;

use App\Content\Enums\ApiKeyScope;
use App\Models\User;
use App\Services\ApiKeyManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiKeyScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/api/test-content-read', fn () => response()->json(['allowed' => true]))
            ->middleware(['api-key.auth', 'api-key.scope:content:read']);
    }

    public function test_legacy_default_key_can_import_media_but_cannot_read_content(): void
    {
        $owner = User::factory()->create();
        $issued = app(ApiKeyManager::class)->create($owner, 'Legacy importer');

        $this->assertSame([ApiKeyScope::MediaImport->value], $issued->key->scopes);

        $this->withBasicAuth($owner->email, $issued->plaintext)
            ->withHeader('Idempotency-Key', 'legacy-import-scope-20260726')
            ->postJson('/api/media/import', ['items' => []])
            ->assertUnprocessable();

        $this->withBasicAuth($owner->email, $issued->plaintext)
            ->getJson('/api/test-content-read')
            ->assertForbidden()
            ->assertJsonPath('code', 'missing_scope');
    }

    public function test_key_with_required_scope_is_allowed_and_unknown_scope_is_never_granted(): void
    {
        $owner = User::factory()->create();
        $issued = app(ApiKeyManager::class)->create(
            $owner,
            'Hermes reader',
            [ApiKeyScope::ContentRead],
        );

        $this->withBasicAuth($owner->email, $issued->plaintext)
            ->getJson('/api/test-content-read')
            ->assertOk()
            ->assertExactJson(['allowed' => true]);

        $this->assertFalse($issued->key->hasScope('content:unknown'));
    }
}
