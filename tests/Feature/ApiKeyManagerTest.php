<?php

namespace Tests\Feature;

use App\Content\Enums\ApiKeyScope;
use App\Models\ApiKey;
use App\Models\User;
use App\Services\ApiKeyManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ApiKeyManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_key_is_issued_once_while_only_its_hash_is_stored(): void
    {
        $user = User::factory()->create();

        $issued = app(ApiKeyManager::class)->create($user, 'Local importer');

        $this->assertMatchesRegularExpression('/^site_live_[a-z0-9]{16}_[a-f0-9]{64}$/', $issued->plaintext);

        [, , $publicId, $secret] = explode('_', $issued->plaintext, 4);
        $stored = ApiKey::query()->sole();

        $this->assertSame($publicId, $stored->public_id);
        $this->assertSame(hash('sha256', $secret), $stored->secret_hash);
        $this->assertSame(substr($secret, -4), $stored->last_four);
        $this->assertSame('Local importer', $stored->name);
        $this->assertSame($user->id, $stored->user_id);
        $this->assertArrayNotHasKey('secret_hash', $stored->toArray());
        $this->assertDatabaseMissing('api_keys', ['secret_hash' => $issued->plaintext]);
    }

    public function test_valid_credentials_authenticate_and_record_last_use(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $issued = app(ApiKeyManager::class)->create($user, 'Importer');

        $authenticated = app(ApiKeyManager::class)->authenticate('OWNER@example.com', $issued->plaintext);

        $this->assertTrue($issued->key->is($authenticated));
        $this->assertNotNull($issued->key->refresh()->last_used_at);
    }

    public function test_invalid_credentials_fail_closed_without_recording_use(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $issued = app(ApiKeyManager::class)->create($user, 'Importer');
        $wrongSecret = substr($issued->plaintext, 0, -1).(str_ends_with($issued->plaintext, '0') ? '1' : '0');

        $manager = app(ApiKeyManager::class);

        $this->assertNull($manager->authenticate('other@example.com', $issued->plaintext));
        $this->assertNull($manager->authenticate($user->email, (string) $wrongSecret));
        $this->assertNull($manager->authenticate($user->email, 'not-a-key'));
        $this->assertNull($issued->key->refresh()->last_used_at);
    }

    public function test_revocation_is_immediate_and_idempotent(): void
    {
        $user = User::factory()->create();
        $manager = app(ApiKeyManager::class);
        $issued = $manager->create($user, 'Importer');

        $manager->revoke($issued->key);
        $revokedAt = $issued->key->refresh()->revoked_at;
        $manager->revoke($issued->key);

        $this->assertNotNull($revokedAt);
        $this->assertTrue($revokedAt->equalTo($issued->key->refresh()->revoked_at));
        $this->assertNull($manager->authenticate($user->email, $issued->plaintext));
    }

    public function test_rotation_creates_a_replacement_and_revokes_the_old_key_immediately(): void
    {
        $user = User::factory()->create();
        $manager = app(ApiKeyManager::class);
        $old = $manager->create($user, 'Importer');

        $replacement = $manager->rotate($old->key);

        $this->assertNotSame($old->plaintext, $replacement->plaintext);
        $this->assertNotNull($old->key->refresh()->revoked_at);
        $this->assertSame($old->key->id, $replacement->key->rotated_from_id);
        $this->assertSame($old->key->user_id, $replacement->key->user_id);
        $this->assertSame($old->key->name, $replacement->key->name);
        $this->assertNull($manager->authenticate($user->email, $old->plaintext));
        $this->assertTrue($replacement->key->is($manager->authenticate($user->email, $replacement->plaintext)));
    }

    public function test_rotation_copies_the_exact_scopes_and_scope_updates_are_normalized(): void
    {
        $user = User::factory()->create();
        $manager = app(ApiKeyManager::class);
        $old = $manager->create($user, 'Hermes', [
            ApiKeyScope::ContentPublish,
            ApiKeyScope::ContentRead,
            ApiKeyScope::ContentRead,
        ]);

        $replacement = $manager->rotate($old->key);

        $this->assertSame([
            ApiKeyScope::ContentPublish->value,
            ApiKeyScope::ContentRead->value,
        ], $replacement->key->scopes);
        $this->assertNotNull($old->key->refresh()->revoked_at);
    }

    public function test_a_revoked_key_cannot_be_rotated_again(): void
    {
        $manager = app(ApiKeyManager::class);
        $issued = $manager->create(User::factory()->create(), 'Importer');
        $manager->revoke($issued->key);

        $this->expectException(LogicException::class);

        $manager->rotate($issued->key);
    }

    public function test_user_relationship_contains_their_keys(): void
    {
        $user = User::factory()->create();
        $issued = app(ApiKeyManager::class)->create($user, 'Importer');

        $this->assertTrue($issued->key->is($user->apiKeys()->sole()));
        $this->assertTrue($user->is($issued->key->user));
    }
}
