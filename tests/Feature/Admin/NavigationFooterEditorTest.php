<?php

namespace Tests\Feature\Admin;

use App\Content\SiteStructureRepository;
use App\Livewire\Admin\NavigationFooterEditor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\IsolatesManagedContent;
use Tests\TestCase;

class NavigationFooterEditorTest extends TestCase
{
    use IsolatesManagedContent;
    use RefreshDatabase;

    public function test_owner_publishes_safe_navigation_and_footer_links(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(NavigationFooterEditor::class)
            ->set('navigationLinks', [
                ['label' => 'Fresh services', 'href' => '/services'],
                ['label' => 'Call', 'href' => 'tel:+15555550199'],
            ])
            ->set('companyLinks', [
                ['label' => 'Areas', 'href' => '/areas'],
            ])
            ->set('resourceLinks', [
                ['label' => 'Sitemap', 'href' => '/sitemap.xml'],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $structure = app(SiteStructureRepository::class);
        $this->assertSame('Fresh services', $structure->navigation()[0]['label']);
        $this->assertSame('Areas', $structure->footer()['company'][0]['label']);
        $this->get('/')->assertOk()->assertSee('Fresh services');
    }

    public function test_unsafe_link_is_rejected_without_publication(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(NavigationFooterEditor::class)
            ->set('navigationLinks', [
                ['label' => 'Unsafe', 'href' => 'javascript:alert(1)'],
            ])
            ->call('save')
            ->assertHasErrors('links');

        $this->assertDatabaseCount('content_entries', 0);
    }

    public function test_missing_internal_destination_is_rejected_without_publication(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(NavigationFooterEditor::class)
            ->set('navigationLinks', [
                ['label' => 'Missing', 'href' => '/this-page-does-not-exist'],
            ])
            ->call('save')
            ->assertHasErrors('links');

        $this->assertDatabaseCount('content_entries', 0);
    }

    public function test_owner_can_retry_after_fixing_an_invalid_link(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(NavigationFooterEditor::class)
            ->set('navigationLinks', [
                ['label' => 'Missing', 'href' => '/this-page-does-not-exist'],
            ])
            ->call('save')
            ->assertHasErrors('links')
            ->set('navigationLinks', [
                ['label' => 'Services', 'href' => '/services'],
            ])
            ->call('save')
            ->assertHasNoErrors('links');

        $this->assertSame(
            'Services',
            app(SiteStructureRepository::class)->navigation()[0]['label'],
        );
    }

    public function test_stale_editor_cannot_overwrite_newer_navigation(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(NavigationFooterEditor::class)
            ->set('navigationLinks', [
                ['label' => 'Baseline', 'href' => '/services'],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $older = Livewire::test(NavigationFooterEditor::class);
        $newer = Livewire::test(NavigationFooterEditor::class);

        $newer
            ->set('navigationLinks', [
                ['label' => 'Newer', 'href' => '/services'],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $older
            ->set('navigationLinks', [
                ['label' => 'Older', 'href' => '/services'],
            ])
            ->call('save')
            ->assertHasErrors('conflict');

        $this->assertSame(
            'Newer',
            app(SiteStructureRepository::class)->navigation()[0]['label'],
        );
    }
}
