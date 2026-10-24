<?php

namespace Tests\Concerns;

use App\Content\AreaRepository;
use App\Content\BenefitRepository;
use App\Content\FaqRepository;
use App\Content\PageRepository;
use App\Content\ProcessStepRepository;
use App\Content\ResourceRepository;
use App\Content\ServiceRepository;
use App\Content\TestimonialRepository;
use Illuminate\Support\Facades\File;

/**
 * A throwaway content directory for tests about what the platform does with
 * pages and records.
 *
 * Every page and record repository is pointed at an empty temporary directory,
 * so the test sees only the fixtures it writes: never the site's own pages and
 * records, which change with the business, and never anything another process
 * is writing into content/ at the same time. Nothing is written to the
 * checkout.
 *
 * Each resolution is a fresh repository, as in production, so a record written
 * mid-test is visible to the next request.
 */
trait UsesFixtureContent
{
    private ?string $fixtureContentRoot = null;

    /**
     * Call from setUp(), after parent::setUp().
     */
    protected function setUpFixtureContent(): void
    {
        $this->fixtureContentRoot = sys_get_temp_dir().'/fixture-content-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->fixtureContentRoot);

        $repositories = [
            PageRepository::class => fn (string $root) => new class($root.'/pages') extends PageRepository
            {
                use ReadsFixtureDirectory;
            },
            ServiceRepository::class => fn (string $root) => new class($root.'/services') extends ServiceRepository
            {
                use ReadsFixtureDirectory;
            },
            AreaRepository::class => fn (string $root) => new class($root.'/areas') extends AreaRepository
            {
                use ReadsFixtureDirectory;
            },
            TestimonialRepository::class => fn (string $root) => new class($root.'/testimonials') extends TestimonialRepository
            {
                use ReadsFixtureDirectory;
            },
            FaqRepository::class => fn (string $root) => new class($root.'/faqs') extends FaqRepository
            {
                use ReadsFixtureDirectory;
            },
            BenefitRepository::class => fn (string $root) => new class($root.'/benefits') extends BenefitRepository
            {
                use ReadsFixtureDirectory;
            },
            ProcessStepRepository::class => fn (string $root) => new class($root.'/process') extends ProcessStepRepository
            {
                use ReadsFixtureDirectory;
            },
            ResourceRepository::class => fn (string $root) => new class($root.'/resources') extends ResourceRepository
            {
                use ReadsFixtureDirectory;
            },
        ];

        $root = $this->fixtureContentRoot;

        foreach ($repositories as $repository => $make) {
            $this->app->bind($repository, fn () => $make($root));
        }
    }

    protected function tearDownFixtureContent(): void
    {
        if ($this->fixtureContentRoot !== null) {
            File::deleteDirectory($this->fixtureContentRoot);
        }

        $this->fixtureContentRoot = null;
    }

    /**
     * A block page at `$slug` ("/" for the homepage). Returns its path.
     *
     * @param  array<int, array{type: string, props?: array<string, mixed>}>  $blocks
     * @param  array<string, mixed>  $overrides
     */
    protected function fixturePage(string $slug, array $blocks, array $overrides = []): string
    {
        $slug = trim($slug, '/');

        $this->writeFixtureFile('pages/'.($slug === '' ? 'home' : $slug), [
            'title' => 'Fixture page',
            'description' => 'A page that exists only for this test.',
            'blocks' => $blocks,
            ...$overrides,
        ]);

        return '/'.$slug;
    }

    /**
     * A homepage with a single hero, for tests that only need the site chrome.
     */
    protected function fixtureHomePage(): string
    {
        return $this->fixturePage('/', [[
            'type' => 'hero/split',
            'props' => [
                'heading' => 'Fixture homepage',
                'body' => 'A homepage that exists only for this test.',
                'cta' => ['label' => 'Send a message', 'href' => '#quote'],
            ],
        ]]);
    }

    /**
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
        $this->writeFixtureFile($directory.'/'.$slug, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeFixtureFile(string $relative, array $data): void
    {
        if ($this->fixtureContentRoot === null) {
            $this->fail('Call setUpFixtureContent() before writing fixture content.');
        }

        $path = $this->fixtureContentRoot.'/'.$relative.'.json';

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
