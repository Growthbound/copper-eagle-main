<?php

namespace Tests\Feature;

use App\Content\ContentValidator;
use App\Content\ManagedContentFileRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Managed page files on disk: which file answers an address.
 *
 * Looking up one page validates only the files that claim its address, so a
 * request does not pay to validate every page on the site. These pin down that
 * the answer is the same one a full scan would give.
 */
class ManagedContentFileRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/managed-files-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_find_returns_the_page_that_claims_the_address(): void
    {
        $this->write('alpha.json', $this->document('alpha', 'Alpha'));
        $this->write('beta.json', $this->document('beta', 'Beta'));

        $page = $this->repository()->find('/beta/');

        $this->assertSame('beta', $page?->slug);
        $this->assertSame('Beta', $page?->title);
        $this->assertNull($this->repository()->find('gamma'));
    }

    public function test_an_unreadable_or_invalid_file_does_not_affect_other_pages(): void
    {
        $this->write('broken.json', '{ not json');
        $this->write('invalid.json', $this->document('invalid', 'Invalid', [['type' => 'not/a-block', 'props' => []]]));
        $this->write('alpha.json', $this->document('alpha', 'Alpha'));

        $this->assertSame('alpha', $this->repository()->find('alpha')?->slug);
        $this->assertNull($this->repository()->find('invalid'));
        $this->assertSame(['alpha'], $this->repository()->all()->pluck('slug')->all());
    }

    public function test_an_invalid_claim_gives_way_to_a_valid_one_for_the_same_address(): void
    {
        $this->write('a-invalid.json', $this->document('shared', 'Invalid claim', [['type' => 'not/a-block', 'props' => []]]));
        $this->write('b-valid.json', $this->document('shared', 'Valid claim'));

        $this->assertSame('Valid claim', $this->repository()->find('shared')?->title);
    }

    public function test_drafts_are_never_served(): void
    {
        $this->write('draft.json', $this->document('draft', 'Draft', status: 'draft'));

        $this->assertNull($this->repository()->find('draft'));
        $this->assertTrue($this->repository()->all()->isEmpty());
    }

    public function test_the_home_page_answers_at_the_root(): void
    {
        $this->write('home.json', $this->document('/', 'Home'));

        $this->assertSame('/', $this->repository()->find('/')?->slug);
        $this->assertSame('/', $this->repository()->find('')?->slug);
    }

    private function repository(): ManagedContentFileRepository
    {
        return new ManagedContentFileRepository(app(ContentValidator::class), $this->directory);
    }

    private function write(string $name, string $contents): void
    {
        File::put($this->directory.'/'.$name, $contents);
    }

    /**
     * @param  list<array<string, mixed>>|null  $blocks
     */
    private function document(string $slug, string $title, ?array $blocks = null, string $status = 'published'): string
    {
        return (string) json_encode([
            'schema_version' => 1,
            'publication_status' => $status,
            'identity' => [
                'kind' => 'page',
                'slug' => $slug,
                'title' => $title,
                'template' => 'standard',
            ],
            'payload' => [
                'title' => $title,
                'noindex' => false,
                'blocks' => $blocks ?? [
                    ['type' => 'hero/interior', 'props' => ['heading' => $title]],
                ],
            ],
        ]);
    }
}
