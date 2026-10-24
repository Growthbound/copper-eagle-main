<?php

namespace Tests\Feature\Admin;

use App\Content\ContentLifecycle;
use App\Content\ContentValidator;
use App\Content\Enums\ContentKind;
use App\Content\Enums\PageTemplate;
use App\Content\PageTemplateRegistry;
use App\Livewire\Admin\ContentIndex;
use App\Models\ContentEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\WritesRecordFixtures;
use Tests\TestCase;

class ContentIndexTest extends TestCase
{
    use RefreshDatabase;
    use WritesRecordFixtures;

    protected function tearDown(): void
    {
        $this->tearDownRecordFixtures();

        parent::tearDown();
    }

    public function test_content_routes_require_an_authenticated_owner(): void
    {
        foreach ([
            '/admin/content',
            '/admin/content/create',
            '/admin/content/1',
            '/admin/content/1/history',
            '/admin/redirects',
            '/admin/site-structure',
        ] as $path) {
            $this->get($path)->assertRedirect(route('admin.login'));
        }
    }

    public function test_owner_can_create_a_standard_draft_and_open_its_editor(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ContentIndex::class)
            ->set('newTitle', 'Fictional owner page')
            ->set('newSlug', 'guides/owner-page')
            ->set('newKind', ContentKind::Page->value)
            ->set('newTemplate', PageTemplate::Standard->value)
            ->call('create')
            ->assertHasNoErrors();

        $entry = ContentEntry::query()->sole();
        $this->assertSame('guides/owner-page', $entry->slug);
        $this->assertSame(1, $entry->revisions()->count());
        $this->assertSame([], $entry->revisions()->sole()->payload['blocks']);
    }

    public function test_index_search_and_status_filters_reduce_visible_records(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $lifecycle = app(ContentLifecycle::class);
        $visible = $lifecycle->createDraft(
            $this->identity('guides/visible', 'Visible guide'),
            $this->payload('Visible guide'),
            $owner,
        );
        $hidden = $lifecycle->createDraft(
            $this->identity('guides/hidden', 'Hidden guide'),
            $this->payload('Hidden guide'),
            $owner,
        );
        $lifecycle->publish($visible, $visible->revisions()->sole(), 0, $owner);

        Livewire::test(ContentIndex::class)
            ->set('search', 'Visible')
            ->assertSee('Visible guide')
            ->assertDontSee('Hidden guide')
            ->set('statusFilter', 'published')
            ->assertSee('Visible guide')
            ->set('search', '')
            ->assertSee('Visible guide')
            ->assertDontSee('Hidden guide');

        $this->assertNotNull($hidden);
    }

    public function test_fixed_template_draft_starts_with_every_locked_section_and_noindex(): void
    {
        // The pillar's service-areas section needs at least one real area.
        $this->fixtureArea();
        $this->actingAs(User::factory()->create());

        Livewire::test(ContentIndex::class)
            ->set('newTitle', 'Draft service pillar')
            ->set('newSlug', 'draft-service-pillar')
            ->set('newTemplate', PageTemplate::ServicePillar->value)
            ->call('create')
            ->assertHasNoErrors();

        $payload = ContentEntry::query()->sole()->revisions()->sole()->payload;
        $definition = app(PageTemplateRegistry::class)->definition(PageTemplate::ServicePillar);

        $this->assertSame($definition->sequence, array_keys($payload['sections']));
        $this->assertTrue($payload['noindex']);
        $this->assertTrue($payload['_scaffold']);
        $this->assertArrayHasKey(
            '/_scaffold',
            app(ContentValidator::class)->validate(
                [
                    'kind' => ContentKind::Page,
                    'slug' => 'draft-service-pillar',
                    'title' => 'Draft service pillar',
                    'template' => PageTemplate::ServicePillar,
                ],
                $payload,
            )->errors(),
        );
    }

    public function test_lifecycle_identity_validation_is_shown_as_a_create_form_error(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ContentIndex::class)
            ->set('newTitle', 'Reserved owner page')
            ->set('newSlug', 'admin')
            ->set('newKind', ContentKind::Page->value)
            ->set('newTemplate', PageTemplate::Standard->value)
            ->call('create')
            ->assertHasErrors('newSlug');

        $this->assertDatabaseCount('content_entries', 0);
    }

    public function test_generic_create_form_cannot_create_site_structure_entries(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ContentIndex::class)
            ->set('newTitle', 'Primary navigation')
            ->set('newSlug', 'primary')
            ->set('newKind', ContentKind::Navigation->value)
            ->set('newTemplate', PageTemplate::Standard->value)
            ->call('create')
            ->assertHasErrors('newKind');

        $this->assertDatabaseCount('content_entries', 0);
    }

    /** @return array<string, mixed> */
    private function identity(string $slug, string $title): array
    {
        return [
            'kind' => ContentKind::Page,
            'slug' => $slug,
            'title' => $title,
            'template' => PageTemplate::Standard,
        ];
    }

    /** @return array<string, mixed> */
    private function payload(string $title): array
    {
        return ['title' => $title, 'blocks' => []];
    }
}
