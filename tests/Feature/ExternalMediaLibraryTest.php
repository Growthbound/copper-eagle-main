<?php

namespace Tests\Feature;

use App\Content\ContentValidator;
use App\Content\MediaIndex;
use App\Content\Validation\ContentValidationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\BindsRegistryFixtures;
use Tests\TestCase;

/**
 * When the media library lives on another install, `/media/43` still has to be
 * a governed reference — just governed by the versioned index rather than by
 * this database.
 *
 * The alternative was copying every binary into the repository to satisfy a
 * local catalog lookup, which is storage this project does not need to carry.
 *
 * The index a site ships is its own data, so these run against the fixture
 * index in `tests/Fixtures/registries/`.
 */
class ExternalMediaLibraryTest extends TestCase
{
    use BindsRegistryFixtures;

    // The media table has to exist for these to mean anything: with no table
    // the validator skips catalog checks entirely, and the bug this guards
    // against only appears on an install that has migrated.
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useMediaIndexFixture();
    }

    public function test_a_library_reference_validates_against_the_index(): void
    {
        config()->set('media.base_url', 'https://library.example');

        $result = $this->validatePageWithImage('/media/43');

        $this->assertTrue($result->isValid(), json_encode($result->errors()));
    }

    public function test_a_library_reference_the_index_does_not_describe_is_rejected(): void
    {
        config()->set('media.base_url', 'https://library.example');

        $result = $this->validatePageWithImage('/media/999999');

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString(
            'the media index does not describe item 999999',
            json_encode($result->errors()),
        );
    }

    public function test_a_video_asset_is_rejected_where_an_image_is_expected(): void
    {
        config()->set('media.base_url', 'https://library.example');

        // 9 stands in for a video the library holds: the index catalogues only
        // still images, so the fixture index does not describe it.
        $result = $this->validatePageWithImage('/media/9');

        $this->assertFalse($result->isValid());
    }

    public function test_the_index_resolves_an_asset_by_its_reference(): void
    {
        $index = app(MediaIndex::class);

        $this->assertSame(43, MediaIndex::referencedId('/media/43'));
        $this->assertSame(43, MediaIndex::referencedId('/media/43/'));
        $this->assertNull(MediaIndex::referencedId('/media/example/finished-project.jpg'));
        $this->assertNull(MediaIndex::referencedId('/build/app.css'));

        $asset = $index->find(43);

        $this->assertNotNull($asset);
        $this->assertSame('first_party', $asset['provenance']);
        $this->assertNull($index->find(999999));
    }

    /**
     * The two reference forms have to agree. A page written with media_id
     * behaved differently from the same page written with src, which took
     * pages off the site on any install with an empty media table.
     */
    public function test_a_media_id_reference_resolves_through_the_index_too(): void
    {
        config()->set('media.base_url', 'https://library.example');

        $result = $this->validatePageWithMediaId(2);

        $this->assertTrue($result->isValid(), json_encode($result->errors()));
    }

    public function test_a_media_id_the_index_does_not_describe_is_still_rejected(): void
    {
        config()->set('media.base_url', 'https://library.example');

        $result = $this->validatePageWithMediaId(999999);

        $this->assertFalse($result->isValid());
    }

    /**
     * Rich-content image nodes resolve media the same way block props do.
     *
     * They did not, and shipped documents — including blog articles — failed
     * validation and vanished behind a 404 on any install whose media table
     * exists but is empty.
     *
     * This checks the documents and index the site actually ships, so the
     * fixture index is swapped back out. A site that ships no managed
     * documents has nothing to fail.
     */
    public function test_every_shipped_document_survives_validation(): void
    {
        $this->app->forgetInstance(MediaIndex::class);

        $validator = app(ContentValidator::class);
        $failed = [];

        $files = File::isDirectory(base_path('content/managed'))
            ? File::allFiles(base_path('content/managed'))
            : [];

        foreach ($files as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }

            $document = json_decode((string) file_get_contents($file->getPathname()), true);

            if (! isset($document['identity'])) {
                continue;
            }

            $result = $validator->validate($document['identity'], $document['payload']);

            if (! $result->isValid()) {
                $failed[] = $document['identity']['slug'].': '.json_encode($result->errors());
            }
        }

        $this->assertSame([], $failed, "These documents would silently 404.\n".implode("\n", $failed));
    }

    private function validatePageWithMediaId(int $mediaId): ContentValidationResult
    {
        return app(ContentValidator::class)->validate(
            ['kind' => 'page', 'slug' => 'media-fixture', 'title' => 'Media fixture', 'template' => 'standard'],
            [
                'title' => 'Media fixture',
                'description' => 'A page used to exercise media reference validation.',
                'blocks' => [[
                    'type' => 'content/split',
                    'props' => [
                        'id' => 'fixture',
                        'heading' => 'A heading',
                        'body' => 'Body copy for the fixture.',
                        'image' => [
                            'media_id' => $mediaId,
                            'src' => '/media/'.$mediaId,
                            'alt' => 'Two crew members unloading equipment from a van',
                            'width' => 1920,
                            'height' => 1080,
                        ],
                    ],
                ]],
            ],
        );
    }

    public function test_repository_copies_remain_usable_without_the_library(): void
    {
        config()->set('media.base_url', '');

        $result = $this->validatePageWithImage('/media/example/finished-project.jpg');

        $this->assertTrue($result->isValid(), json_encode($result->errors()));
    }

    private function validatePageWithImage(string $src): ContentValidationResult
    {
        return app(ContentValidator::class)->validate(
            ['kind' => 'page', 'slug' => 'media-fixture', 'title' => 'Media fixture', 'template' => 'standard'],
            [
                'title' => 'Media fixture',
                'description' => 'A page used to exercise media reference validation.',
                'blocks' => [
                    [
                        'type' => 'content/split',
                        'props' => [
                            'id' => 'fixture',
                            'heading' => 'A heading',
                            'body' => 'Body copy for the fixture.',
                            'image' => [
                                'src' => $src,
                                'alt' => 'A finished project photographed on site',
                                'width' => 601,
                                'height' => 802,
                            ],
                        ],
                    ],
                ],
            ],
        );
    }
}
