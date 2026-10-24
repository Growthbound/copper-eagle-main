<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Leads;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeadsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_leads_can_be_filtered_by_status(): void
    {
        Lead::query()->create(['name' => 'New Request']);
        Lead::query()->create(['name' => 'Old Request'])->markAs('archived');

        Livewire::test(Leads::class)
            ->assertSee('New Request')
            ->assertSee('Old Request')
            ->set('status', 'archived')
            ->assertDontSee('New Request')
            ->assertSee('Old Request');
    }

    public function test_leads_can_be_searched_across_contact_details(): void
    {
        Lead::query()->create(['name' => 'Taylor Example', 'email' => 'taylor@example.test']);
        Lead::query()->create(['name' => 'Sam Fox', 'email' => 'sam@example.test']);

        Livewire::test(Leads::class)
            ->set('search', 'taylor@')
            ->assertSee('Taylor Example')
            ->assertDontSee('Sam Fox');
    }

    public function test_marking_a_lead_handled_records_when_it_happened(): void
    {
        $lead = Lead::query()->create(['name' => 'Taylor Example']);

        Livewire::test(Leads::class)->call('markAs', $lead->id, 'handled');

        $lead->refresh();

        $this->assertSame('handled', $lead->status);
        $this->assertNotNull($lead->handled_at);
    }

    public function test_a_lead_can_be_deleted(): void
    {
        $lead = Lead::query()->create(['name' => 'Taylor Example']);

        Livewire::test(Leads::class)->call('delete', $lead->id);

        $this->assertSame(0, Lead::query()->count());
    }

    public function test_the_export_returns_only_the_filtered_leads_as_csv(): void
    {
        Lead::query()->create(['name' => 'Taylor Example', 'email' => 'taylor@example.test']);
        Lead::query()->create(['name' => 'Sam Fox'])->markAs('archived');

        $download = Livewire::test(Leads::class)
            ->set('status', 'new')
            ->instance()
            ->export();

        ob_start();
        $download->sendContent();
        $csv = (string) ob_get_clean();

        $this->assertStringContainsString('Taylor Example', $csv);
        $this->assertStringContainsString('taylor@example.test', $csv);
        $this->assertStringNotContainsString('Sam Fox', $csv);
    }
}
