<?php

namespace Tests\Feature;

use App\Content\Enums\ContentSource;
use App\Content\Enums\ContentStatus;
use App\Models\ContentEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImportJsonContentTest extends TestCase
{
    use RefreshDatabase;

    private string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixturePath = base_path('content/pages/import-fixture.json');
        File::ensureDirectoryExists(dirname($this->fixturePath));
        File::put($this->fixturePath, json_encode($this->payload('Legacy JSON heading'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function tearDown(): void
    {
        File::delete($this->fixturePath);

        parent::tearDown();
    }

    public function test_dry_run_validates_but_writes_nothing_and_does_not_modify_json(): void
    {
        User::factory()->create();
        $before = hash_file('sha256', $this->fixturePath);

        $this->artisan('content:import-json', [
            '--dry-run' => true,
            '--kind' => ['page'],
            '--slug' => ['import-fixture'],
        ])->assertSuccessful();

        $this->assertDatabaseCount('content_entries', 0);
        $this->assertSame($before, hash_file('sha256', $this->fixturePath));
    }

    public function test_import_creates_one_deterministic_draft_without_replacing_public_json(): void
    {
        User::factory()->create();
        $sourceHash = hash_file('sha256', $this->fixturePath);

        $this->get('/import-fixture')
            ->assertOk()
            ->assertSee('Legacy JSON heading');

        $this->artisan('content:import-json', [
            '--slug' => ['import-fixture'],
        ])->assertSuccessful();
        $this->artisan('content:import-json', [
            '--slug' => ['import-fixture'],
        ])->assertSuccessful();

        $entry = ContentEntry::query()->where('slug', 'import-fixture')->sole();
        $revision = $entry->revisions()->sole();

        $this->assertSame(ContentStatus::Draft, $entry->status);
        $this->assertNull($entry->published_revision_id);
        $this->assertSame(ContentSource::Import, $revision->source);
        $this->assertStringContainsString('content/pages/import-fixture.json', $revision->summary);
        $this->assertStringContainsString($sourceHash, $revision->summary);
        $this->assertSame('Legacy JSON heading', $revision->payload['title']);
        $this->assertSame($sourceHash, hash_file('sha256', $this->fixturePath));
        $this->get('/import-fixture')
            ->assertOk()
            ->assertSee('Legacy JSON heading');
    }

    public function test_changed_source_adds_one_revision_to_the_same_draft(): void
    {
        User::factory()->create();
        $this->artisan('content:import-json', ['--slug' => ['import-fixture']])
            ->assertSuccessful();

        File::put($this->fixturePath, json_encode($this->payload('Updated legacy heading'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->artisan('content:import-json', ['--slug' => ['import-fixture']])
            ->assertSuccessful();

        $entry = ContentEntry::query()->where('slug', 'import-fixture')->sole();
        $this->assertSame(2, $entry->revisions()->count());
        $this->assertSame(1, $entry->lock_version);
        $this->assertSame(
            'Updated legacy heading',
            $entry->revisions()->latest('revision_number')->firstOrFail()->payload['title'],
        );
    }

    /** @return array<string, mixed> */
    private function payload(string $heading): array
    {
        return [
            'title' => $heading,
            'description' => 'A fictional legacy JSON page.',
            'blocks' => [[
                'type' => 'hero/split',
                'props' => [
                    'heading' => $heading,
                ],
            ]],
        ];
    }
}
