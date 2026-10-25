<?php

namespace Tests\Feature;

use App\Content\ContentLifecycle;
use App\Content\Enums\ContentKind;
use App\Content\Enums\ContentSource;
use App\Content\Enums\PageTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CheckContentCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_check_fails_for_an_invalid_managed_publication(): void
    {
        $user = User::factory()->create();
        $lifecycle = app(ContentLifecycle::class);
        $identity = [
            'kind' => ContentKind::Page,
            'slug' => 'managed-check',
            'title' => 'Managed check',
            'template' => PageTemplate::Standard,
        ];
        $entry = $lifecycle->createDraft(
            $identity,
            $this->payload('/services'),
            $user,
        );
        $entry = $lifecycle->publish(
            $entry,
            $entry->revisions()->sole(),
            0,
            $user,
        );
        $invalid = $entry->revisions()->create([
            'revision_number' => 2,
            'schema_version' => 1,
            'identity' => [
                'kind' => ContentKind::Page->value,
                'slug' => 'managed-check',
                'title' => 'Managed check',
                'template' => PageTemplate::Standard->value,
            ],
            'payload' => $this->payload('/missing-managed-destination'),
            'created_by' => $user->id,
            'source' => ContentSource::Admin,
        ]);
        $entry->forceFill(['published_revision_id' => $invalid->id])->save();

        $this->artisan('content:check')
            ->expectsOutputToContain('managed page/managed-check')
            ->assertFailed();
    }

    public function test_content_check_skips_managed_records_before_a_sqlite_database_exists(): void
    {
        $missing = storage_path('framework/testing/missing-content-check.sqlite');
        File::delete($missing);
        config()->set('database.default', 'missing-content-check');
        config()->set('database.connections.missing-content-check', [
            'driver' => 'sqlite',
            'database' => $missing,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Only managed files on disk are counted; the publications table in a
        // database that does not exist yet is skipped rather than created.
        $managedFiles = File::isDirectory(base_path('content/managed'))
            ? collect(File::allFiles(base_path('content/managed')))
                ->filter(fn ($file): bool => $file->getExtension() === 'json')
                ->count()
            : 0;

        try {
            $this->artisan('content:check')
                ->expectsOutputToContain($managedFiles.' managed publication(s)')
                ->assertSuccessful();

            $this->assertFileDoesNotExist($missing);
        } finally {
            DB::purge('missing-content-check');
            config()->set('database.default', 'sqlite');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $href): array
    {
        return [
            'title' => 'Managed check',
            'blocks' => [[
                'type' => 'cta/inline-band',
                'props' => [
                    'heading' => 'Next step',
                    'primaryAction' => [
                        'label' => 'Continue',
                        'href' => $href,
                    ],
                ],
            ]],
        ];
    }
}
