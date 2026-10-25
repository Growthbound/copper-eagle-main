<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ApiKeyManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiKeyAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api-key.auth')->get('/api/_test/key-auth', function (Request $request) {
            return response()->json([
                'email' => $request->user()?->email,
                'key_id' => $request->attributes->get('api_key')?->id,
            ]);
        });
    }

    public function test_missing_credentials_receive_the_generic_basic_auth_challenge(): void
    {
        $this->getJson('/api/_test/key-auth')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Basic realm="Content API"')
            ->assertExactJson(['message' => 'Invalid API credentials.']);
    }

    public function test_malformed_unknown_and_incorrect_credentials_share_one_response(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $issued = app(ApiKeyManager::class)->create($owner, 'Importer');
        $wrongKey = substr($issued->plaintext, 0, -1).(str_ends_with($issued->plaintext, '0') ? '1' : '0');

        $attempts = [
            ['owner@example.com', 'not-a-key'],
            ['unknown@example.com', $issued->plaintext],
            ['owner@example.com', $wrongKey],
        ];

        foreach ($attempts as [$email, $key]) {
            $this->withBasicAuth($email, $key)
                ->getJson('/api/_test/key-auth')
                ->assertUnauthorized()
                ->assertExactJson(['message' => 'Invalid API credentials.']);
        }

        $this->assertNull($issued->key->refresh()->last_used_at);
    }

    public function test_a_revoked_key_is_rejected_immediately(): void
    {
        $owner = User::factory()->create();
        $manager = app(ApiKeyManager::class);
        $issued = $manager->create($owner, 'Importer');
        $manager->revoke($issued->key);

        $this->withBasicAuth($owner->email, $issued->plaintext)
            ->getJson('/api/_test/key-auth')
            ->assertUnauthorized();
    }

    public function test_a_valid_key_resolves_its_owner_and_request_attribute_and_tracks_use(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $issued = app(ApiKeyManager::class)->create($owner, 'Importer');

        $this->withBasicAuth('OWNER@example.com', $issued->plaintext)
            ->getJson('/api/_test/key-auth')
            ->assertOk()
            ->assertExactJson([
                'email' => $owner->email,
                'key_id' => $issued->key->id,
            ]);

        $this->assertNotNull($issued->key->refresh()->last_used_at);
        $this->assertGuest();
    }
}
