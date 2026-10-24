<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\File;

/**
 * Temporary service and area records for tests about the record-to-page
 * mechanism itself.
 *
 * Observing that mechanism through a site's own records would make platform
 * behaviour depend on one brand's editorial decisions, and would break the
 * moment the URL intent ledger folded those pages into their pillars: the
 * mechanism would still work, but every page the tests watched would have
 * become a redirect.
 *
 * A fixture record keeps the subject of the test the platform, which is what
 * the repository contract asks for.
 */
trait WritesRecordFixtures
{
    /** @var array<int, string> */
    private array $recordFixtures = [];

    protected function tearDownRecordFixtures(): void
    {
        foreach ($this->recordFixtures as $path) {
            File::delete($path);
        }

        $this->recordFixtures = [];
    }

    /**
     * A service that no ledger disposition touches, so its generated page
     * answers 200 for as long as the mechanism works.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function fixtureService(string $slug = 'fixture-service', array $overrides = []): string
    {
        $this->writeRecordFixture('services', $slug, [
            'name' => 'Fixture Service',
            'summary' => 'A service that exists only for this test.',
            'description' => 'What the fixture service involves.',
            ...$overrides,
        ]);

        return '/services/'.$slug;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function fixtureArea(string $slug = 'fixture-area', array $overrides = []): string
    {
        $this->writeRecordFixture('areas', $slug, [
            'name' => 'Fixture Area',
            'region' => 'Example State',
            'summary' => 'An area that exists only for this test.',
            'landmarks' => ['Fixture Park'],
            ...$overrides,
        ]);

        return '/areas/'.$slug;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function writeRecordFixture(string $directory, string $slug, array $data): void
    {
        $path = base_path('content/'.$directory.'/'.$slug.'.json');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->recordFixtures[] = $path;
    }
}
