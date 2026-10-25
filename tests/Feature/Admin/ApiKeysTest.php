<?php

namespace Tests\Feature\Admin;

use App\Content\Enums\ApiKeyScope;
use App\Livewire\Admin\ApiKeys;
use App\Models\ApiKey;
use App\Models\User;
use App\Services\ApiKeyManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApiKeysTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_keys_page_is_in_the_admin_navigation(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.keys'))
            ->assertSee('API keys');
    }

    public function test_an_owner_can_generate_and_dismiss_a_one_time_key(): void
    {
        $owner = User::factory()->create();

        $component = Livewire::actingAs($owner)
            ->test(ApiKeys::class)
            ->set('name', 'Local importer')
            ->call('generate')
            ->assertHasNoErrors()
            ->assertSet('name', '')
            ->assertSee('Copy this key now');

        $plaintext = $component->get('newKey');

        $this->assertIsString($plaintext);
        $this->assertMatchesRegularExpression('/^site_live_[a-z0-9]{16}_[a-f0-9]{64}$/', $plaintext);
        $this->assertSame($owner->id, ApiKey::query()->sole()->user_id);

        $component
            ->assertSee($plaintext)
            ->call('dismissPlaintext')
            ->assertSet('newKey', null)
            ->assertDontSee($plaintext);
    }

    public function test_a_key_name_is_required_and_limited(): void
    {
        $component = Livewire::actingAs(User::factory()->create())->test(ApiKeys::class);

        $component->set('name', '')->call('generate')->assertHasErrors(['name' => 'required']);
        $component->set('name', str_repeat('a', 121))->call('generate')->assertHasErrors(['name' => 'max']);

        $this->assertSame(0, ApiKey::query()->count());
    }

    public function test_owner_selects_scopes_and_can_update_an_active_key(): void
    {
        $owner = User::factory()->create();

        $component = Livewire::actingAs($owner)
            ->test(ApiKeys::class)
            ->set('name', 'Hermes')
            ->set('selectedScopes', [
                ApiKeyScope::ContentRead->value,
                ApiKeyScope::ContentPublish->value,
            ])
            ->call('generate')
            ->assertHasNoErrors();

        $key = ApiKey::query()->sole();
        $this->assertSame([
            ApiKeyScope::ContentRead->value,
            ApiKeyScope::ContentPublish->value,
        ], $key->scopes);

        $component
            ->set('keyScopes.'.$key->id, [ApiKeyScope::MediaRead->value])
            ->call('saveScopes', $key->id)
            ->assertHasNoErrors();

        $this->assertSame([ApiKeyScope::MediaRead->value], $key->fresh()->scopes);
    }

    public function test_rotation_revokes_the_old_key_immediately_and_shows_the_replacement_once(): void
    {
        $owner = User::factory()->create();
        $manager = app(ApiKeyManager::class);
        $old = $manager->create($owner, 'Local importer');

        $component = Livewire::actingAs($owner)
            ->test(ApiKeys::class)
            ->call('rotate', $old->key->id)
            ->assertSee('Old key revoked immediately')
            ->assertSee('Copy this key now');

        $replacement = ApiKey::query()->whereNull('revoked_at')->sole();

        $this->assertNotNull($old->key->refresh()->revoked_at);
        $this->assertSame($old->key->id, $replacement->rotated_from_id);
        $this->assertSame($replacement->id, $component->get('newKeyId'));
        $this->assertStringStartsWith('site_live_'.$replacement->public_id.'_', $component->get('newKey'));
    }

    public function test_an_owner_can_revoke_an_active_key(): void
    {
        $owner = User::factory()->create();
        $issued = app(ApiKeyManager::class)->create($owner, 'Local importer');

        Livewire::actingAs($owner)
            ->test(ApiKeys::class)
            ->call('revoke', $issued->key->id)
            ->assertSee('Key revoked immediately.');

        $this->assertNotNull($issued->key->refresh()->revoked_at);
    }

    public function test_active_and_revoked_keys_are_both_retained_in_the_audit_list(): void
    {
        $owner = User::factory()->create();
        $manager = app(ApiKeyManager::class);
        $active = $manager->create($owner, 'Active importer');
        $revoked = $manager->create($owner, 'Old importer');
        $manager->revoke($revoked->key);

        Livewire::actingAs($owner)
            ->test(ApiKeys::class)
            ->assertSee('Active importer')
            ->assertSee($active->key->displayPrefix())
            ->assertSee('Old importer')
            ->assertSee($revoked->key->displayPrefix())
            ->assertSee('Active')
            ->assertSee('Revoked');
    }

    public function test_an_owner_cannot_rotate_another_users_key(): void
    {
        $owner = User::factory()->create();
        $otherKey = app(ApiKeyManager::class)->create(User::factory()->create(), 'Other key');

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($owner)->test(ApiKeys::class)->call('rotate', $otherKey->key->id);
    }

    public function test_an_owner_cannot_revoke_another_users_key(): void
    {
        $owner = User::factory()->create();
        $otherKey = app(ApiKeyManager::class)->create(User::factory()->create(), 'Other key');

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($owner)->test(ApiKeys::class)->call('revoke', $otherKey->key->id);
    }
}
