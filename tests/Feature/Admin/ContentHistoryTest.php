<?php

namespace Tests\Feature\Admin;

use App\Content\ContentLifecycle;
use App\Content\Enums\ApiKeyScope;
use App\Content\Enums\ContentKind;
use App\Content\Enums\ContentSource;
use App\Content\Enums\PageTemplate;
use App\Livewire\Admin\ContentHistory;
use App\Models\User;
use App\Services\ApiKeyManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContentHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_shows_pointer_diff_and_owner_can_roll_back(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft(
            [
                'kind' => ContentKind::Page,
                'slug' => 'guides/history',
                'title' => 'History guide',
                'template' => PageTemplate::Standard,
            ],
            $this->payload('First description'),
            $owner,
        );
        $first = $entry->revisions()->sole();
        $second = $lifecycle->saveRevision(
            $entry,
            $this->payload('Second description'),
            0,
            $owner,
            ContentSource::Admin,
        );
        $lifecycle->publish($entry->fresh(), $second, 1, $owner);

        Livewire::test(ContentHistory::class, ['entry' => $entry->fresh()])
            ->set('fromRevisionId', $first->id)
            ->set('toRevisionId', $second->id)
            ->assertSee('/description')
            ->assertSee('First description')
            ->assertSee('Second description')
            ->call('rollback', $first->id)
            ->assertHasNoErrors();

        $entry->refresh();
        $this->assertSame(3, $entry->revisions()->count());
        $this->assertSame('First description', $entry->publishedRevision->payload['description']);
    }

    public function test_long_machine_revision_notes_are_allowed_to_wrap_on_narrow_screens(): void
    {
        $owner = User::factory()->create();
        $entry = app(ContentLifecycle::class)->createDraft(
            [
                'kind' => ContentKind::Page,
                'slug' => 'guides/wrapping',
                'title' => 'Wrapping guide',
                'template' => PageTemplate::Standard,
            ],
            $this->payload('Initial description'),
            $owner,
        );
        $entry->revisions()->sole()->forceFill([
            'summary' => 'import:content/pages/home.json:sha256:'.str_repeat('a', 64),
        ])->saveQuietly();

        $this->actingAs($owner)
            ->get(route('admin.content.history', $entry))
            ->assertOk()
            ->assertSee('<caption class="sr-only">Changes between the selected revisions</caption>', false)
            ->assertSee('<th scope="col"', false)
            ->assertSee('min-w-0', false)
            ->assertSee('break-all', false);
    }

    public function test_history_includes_revision_identity_changes(): void
    {
        $owner = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $entry = $lifecycle->createDraft([
            'kind' => ContentKind::Page,
            'slug' => 'guides/original',
            'title' => 'Original guide',
            'template' => PageTemplate::Standard,
        ], $this->payload('Same payload'), $owner);
        $first = $entry->revisions()->sole();
        $second = $lifecycle->saveRevision(
            $entry,
            $first->payload,
            0,
            $owner,
            ContentSource::Admin,
            identity: [
                'kind' => ContentKind::Page,
                'slug' => 'guides/renamed',
                'title' => 'Renamed guide',
                'template' => PageTemplate::Standard,
            ],
        );

        $this->actingAs($owner);
        Livewire::test(ContentHistory::class, ['entry' => $entry->fresh()])
            ->set('fromRevisionId', $first->id)
            ->set('toRevisionId', $second->id)
            ->assertSee('/identity/slug')
            ->assertSee('guides/original')
            ->assertSee('guides/renamed');
    }

    public function test_history_displays_safe_api_key_attribution_for_automation_events(): void
    {
        $owner = User::factory()->create();
        $issued = app(ApiKeyManager::class)->create(
            $owner,
            'Hermes content writer',
            [ApiKeyScope::ContentDraft],
        );
        $entry = app(ContentLifecycle::class)->createDraft([
            'kind' => ContentKind::Page,
            'slug' => 'guides/api-history',
            'title' => 'API history',
            'template' => PageTemplate::Standard,
        ], $this->payload('API-created revision'), $owner, $issued->key);
        $this->actingAs($owner);

        Livewire::test(ContentHistory::class, ['entry' => $entry])
            ->assertSee('Hermes content writer')
            ->assertSee($issued->key->displayPrefix())
            ->assertDontSee($issued->plaintext);
    }

    /** @return array<string, mixed> */
    private function payload(string $description): array
    {
        return [
            'title' => 'History guide',
            'description' => $description,
            'blocks' => [],
        ];
    }
}
