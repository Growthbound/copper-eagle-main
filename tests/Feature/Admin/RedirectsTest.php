<?php

namespace Tests\Feature\Admin;

use App\Content\Exceptions\ContentValidationFailed;
use App\Content\RedirectLifecycle;
use App\Livewire\Admin\Redirects;
use App\Models\RedirectAuditEvent;
use App\Models\RedirectRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RedirectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_edit_disable_and_delete_a_valid_redirect(): void
    {
        $this->actingAs(User::factory()->create());

        $component = Livewire::test(Redirects::class)
            ->set('sourcePath', '/legacy/old-page')
            ->set('target', '/services/example-service')
            ->set('statusCode', 301)
            ->call('save')
            ->assertHasNoErrors();

        $rule = RedirectRule::query()->sole();
        $component
            ->call('edit', $rule->id)
            ->set('notes', 'Approved equivalent replacement.')
            ->call('save')
            ->assertHasNoErrors()
            ->call('toggle', $rule->id)
            ->assertHasNoErrors();

        $this->assertFalse($rule->fresh()->enabled);
        $this->assertSame('Approved equivalent replacement.', $rule->fresh()->notes);

        $component->call('delete', $rule->id)->assertHasNoErrors();
        $this->assertDatabaseCount('redirect_rules', 0);
        $this->assertSame(
            ['create', 'update', 'toggle', 'delete'],
            RedirectAuditEvent::query()->orderBy('id')->pluck('action')->all(),
        );
        $deleted = RedirectAuditEvent::query()->where('action', 'delete')->sole();
        $this->assertNull($deleted->redirect_rule_id);
        $this->assertSame('/legacy/old-page', $deleted->before['source_path']);
        $this->assertNull($deleted->after);
    }

    public function test_invalid_or_chained_redirect_is_rejected_without_writing(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        RedirectRule::create([
            'source_path' => '/legacy/first',
            'target' => '/services/final',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        Livewire::test(Redirects::class)
            ->set('sourcePath', '/legacy/second')
            ->set('target', '/legacy/first')
            ->call('save')
            ->assertHasErrors('target');

        $this->assertDatabaseCount('redirect_rules', 1);
    }

    public function test_redirect_lifecycle_revalidates_the_graph_inside_the_shared_lock(): void
    {
        $owner = User::factory()->create();
        $lifecycle = app(RedirectLifecycle::class);
        $lifecycle->create([
            'source_path' => '/a',
            'target' => '/b',
            'status_code' => 301,
            'enabled' => true,
        ], $owner);

        try {
            $lifecycle->create([
                'source_path' => '/b',
                'target' => '/a',
                'status_code' => 301,
                'enabled' => true,
            ], $owner);
            $this->fail('Complementary redirect changes must not create a loop.');
        } catch (ContentValidationFailed $failure) {
            $this->assertTrue(
                array_key_exists('/source_path', $failure->result->errors())
                    || array_key_exists('/target', $failure->result->errors()),
            );
        }

        $this->assertDatabaseCount('redirect_rules', 1);
        $this->assertDatabaseHas('content_operation_locks', [
            'name' => 'redirect-graph',
        ]);
    }
}
