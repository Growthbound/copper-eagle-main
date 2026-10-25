<?php

namespace Tests\Feature;

use App\Content\InvalidPage;
use App\Content\PageRepository;
use Illuminate\Support\Facades\File;
use Tests\Concerns\IsolatesManagedContent;
use Tests\TestCase;

class PageSystemTest extends TestCase
{
    use IsolatesManagedContent;

    /** @var array<int, string> */
    private array $temporaryPages = [];

    /** @var array<int, string> */
    private array $createdDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryPages as $path) {
            File::delete($path);
        }

        // Deepest first, and only when empty, so a directory made for a
        // nested page file does not outlive the test.
        foreach (array_reverse($this->createdDirectories) as $directory) {
            if (File::isDirectory($directory) && File::isEmptyDirectory($directory)) {
                File::deleteDirectory($directory);
            }
        }

        $this->temporaryPages = [];
        $this->createdDirectories = [];

        parent::tearDown();
    }

    public function test_the_homepage_is_served_from_a_page_file(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee($this->homepageHeadlineMarkup(), false);
    }

    public function test_a_page_file_becomes_a_url(): void
    {
        $this->writePage('test-about-us', [
            'title' => 'About our crew',
            'description' => 'Who we are.',
            'blocks' => [
                ['type' => 'cta/panel', 'props' => ['heading' => 'Meet the team']],
            ],
        ]);

        $this->get('/test-about-us')
            ->assertOk()
            ->assertSee('Meet the team')
            ->assertSee('<title>About our crew | '.e(config('site.name')).'</title>', false)
            ->assertSee('content="Who we are."', false);
    }

    public function test_a_nested_page_file_becomes_a_nested_url(): void
    {
        $this->writePage('services/fixture-nested-page', [
            'title' => 'Nested page',
            'blocks' => [
                ['type' => 'cta/panel', 'props' => ['heading' => 'Book a nested visit']],
            ],
        ]);

        $this->get('/services/fixture-nested-page')
            ->assertOk()
            ->assertSee('Book a nested visit');
    }

    public function test_an_unknown_url_is_not_found(): void
    {
        $this->get('/no-such-page')->assertNotFound();
    }

    public function test_a_page_cannot_reach_outside_the_content_directory(): void
    {
        $this->assertNull(app(PageRepository::class)->find('../../config/app'));
    }

    public function test_a_noindex_page_says_so_and_stays_out_of_the_sitemap(): void
    {
        $this->writePage('thank-you', [
            'title' => 'Thank you',
            'noindex' => true,
            'blocks' => [
                ['type' => 'cta/panel', 'props' => ['heading' => 'Thanks for reaching out']],
            ],
        ]);

        $this->get('/thank-you')
            ->assertOk()
            ->assertSee('name="robots" content="noindex, nofollow"', false);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertDontSee('thank-you');
    }

    public function test_the_sitemap_lists_every_indexable_page(): void
    {
        $this->writePage('warranty', [
            'title' => 'Warranty',
            'blocks' => [['type' => 'cta/panel', 'props' => ['heading' => 'Our warranty']]],
        ]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('/warranty');
    }

    public function test_a_page_missing_a_title_is_rejected_with_a_readable_reason(): void
    {
        $this->writePage('broken', ['blocks' => []]);

        try {
            app(PageRepository::class)->find('broken');
            $this->fail('An untitled page should not load.');
        } catch (InvalidPage $e) {
            $this->assertStringContainsString('needs a "title"', $e->getMessage());
            $this->assertStringContainsString('content/pages/broken.json', $e->describe());
        }
    }

    public function test_malformed_json_names_the_file_and_the_problem(): void
    {
        $path = base_path('content/pages/malformed.json');
        $this->ensureDirectory(dirname($path));
        File::put($path, '{"title": "Oops",}');
        $this->temporaryPages[] = $path;

        try {
            app(PageRepository::class)->find('malformed');
            $this->fail('Malformed JSON should not load.');
        } catch (InvalidPage $e) {
            $this->assertStringContainsString('not valid JSON', $e->getMessage());
        }
    }

    public function test_content_check_passes_for_the_shipped_pages(): void
    {
        $this->restoreManagedContent();

        $this->artisan('content:check')->assertSuccessful();
    }

    public function test_content_check_fails_on_a_block_type_that_does_not_exist(): void
    {
        $this->writePage('bad-block', [
            'title' => 'Bad block',
            'description' => 'Testing.',
            'blocks' => [['type' => 'hero/does-not-exist']],
        ]);

        $this->artisan('content:check')
            ->expectsOutputToContain('hero/does-not-exist')
            ->assertFailed();
    }

    public function test_content_check_warns_about_a_missing_description(): void
    {
        $this->restoreManagedContent();
        $this->writePage('no-description', [
            'title' => 'No description',
            'blocks' => [['type' => 'cta/panel', 'props' => ['heading' => 'Hello']]],
        ]);

        $this->artisan('content:check')
            ->expectsOutputToContain('no-description')
            ->assertSuccessful();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writePage(string $slug, array $data): void
    {
        $path = base_path('content/pages/'.$slug.'.json');

        $this->ensureDirectory(dirname($path));
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->temporaryPages[] = $path;
    }

    private function ensureDirectory(string $directory): void
    {
        if (File::isDirectory($directory)) {
            return;
        }

        $this->ensureDirectory(dirname($directory));

        File::makeDirectory($directory, 0755, false, true);
        $this->createdDirectories[] = $directory;
    }

    /**
     * The homepage headline, read from the page file rather than hardcoded.
     *
     * These tests are about publication precedence and page rendering, not
     * about the wording. Pinning the copy made every brand-voice edit break
     * tests that have nothing to do with copy.
     *
     * Matched as the hero's heading element rather than bare text: the
     * placeholder headline can be the site name, which the header and footer
     * show on every page anyway.
     */
    private function homepageHeadlineMarkup(): string
    {
        $page = json_decode(
            (string) file_get_contents(base_path('content/pages/home.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        return '<h1 id="hero-title">'.e($page['blocks'][0]['props']['heading']).'</h1>';
    }

    private function homepageTitle(): string
    {
        $page = json_decode(
            (string) file_get_contents(base_path('content/pages/home.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        return $page['title'];
    }
}
