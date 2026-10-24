<?php

namespace Tests\Feature;

use App\Content\MediaIndex;
use Tests\Concerns\BindsRegistryFixtures;
use Tests\TestCase;

/**
 * The URL families WordPress generated around the pages, which a page-level
 * migration inventory cannot see.
 *
 * Which families a site declares is its own data, so these run against the
 * fixture registry and media index in `tests/Fixtures/registries/`.
 */
class LegacyWordPressUrlTest extends TestCase
{
    use BindsRegistryFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useLegacyUrlFixture();
    }

    public function test_an_uploaded_file_resolves_to_its_library_id(): void
    {
        // The fixture index records Example-photo.png as asset 42.
        $this->get('/wp-content/uploads/Example-photo.png')
            ->assertStatus(301)
            ->assertRedirect(url('/media/42'));
    }

    /**
     * WordPress switches uploads to year/month folders on a settings change,
     * and URLs from before such a change stay indexed.
     */
    public function test_a_dated_upload_path_resolves_to_the_same_file(): void
    {
        $this->get('/wp-content/uploads/2024/05/Example-photo.png')
            ->assertStatus(301)
            ->assertRedirect(url('/media/42'));
    }

    /**
     * WordPress publishes every generated size as its own indexable URL, but
     * only the original was imported.
     */
    public function test_a_generated_size_falls_back_to_the_original(): void
    {
        $this->get('/wp-content/uploads/Example-photo-300x200.png')
            ->assertStatus(301)
            ->assertRedirect(url('/media/42'));
    }

    /**
     * WordPress stores a downscaled `-scaled` copy as the working file for
     * large uploads while its pages still reference the pre-scale name, so the
     * library records one name and the indexed URLs use another.
     */
    public function test_a_pre_scale_name_reaches_its_scaled_library_entry(): void
    {
        $index = app(MediaIndex::class);
        $scaled = collect($index->assets())
            ->first(fn (array $asset): bool => str_contains($asset['original_name'], '-scaled.'));

        $this->assertNotNull($scaled, 'The fixture library has no -scaled asset to exercise.');

        $preScale = str_replace('-scaled.', '.', $scaled['original_name']);

        $this->assertSame(
            $scaled['id'],
            $index->idForOriginalName($preScale),
            "{$preScale} should reach {$scaled['original_name']}.",
        );
    }

    /**
     * The comment claims the lowest id wins. Taking whichever duplicate appears
     * first in the file would make a permanent redirect depend on array order.
     */
    public function test_a_duplicated_filename_resolves_to_the_lowest_id(): void
    {
        $index = app(MediaIndex::class);
        $byName = [];

        foreach ($index->assets() as $asset) {
            $byName[strtolower($asset['original_name'])][] = $asset['id'];
        }

        $duplicated = array_filter($byName, fn (array $ids): bool => count($ids) > 1);

        $this->assertNotSame([], $duplicated, 'No duplicated filename left to exercise.');

        foreach ($duplicated as $name => $ids) {
            $this->assertSame(min($ids), $index->idForOriginalName($name));
        }
    }

    /**
     * A redirect to the wrong picture is worse than a missing one, so an
     * unknown filename is left to 404 rather than guessed at.
     */
    public function test_an_unknown_upload_is_not_guessed_at(): void
    {
        $this->get('/wp-content/uploads/never-imported-abc123.jpg')->assertNotFound();
    }

    public function test_paginated_archives_fold_back_into_the_archive(): void
    {
        foreach (['/blog/page/2/', '/blog/page/7', '/blog/page/12/'] as $path) {
            $this->assertRedirectsTo('/blog/', $path);
        }
    }

    public function test_the_feeds_fold_into_the_archive(): void
    {
        foreach (['/feed/', '/feed', '/blog/feed/', '/comments/feed/'] as $path) {
            $this->assertRedirectsTo('/blog/', $path);
        }
    }

    /**
     * Search Console keeps requesting whichever sitemap URL was submitted, and
     * Rank Math submits the index rather than the plain file.
     */
    public function test_the_old_sitemap_urls_reach_the_new_sitemap(): void
    {
        // The index aliases, and the child sitemaps the index exposes directly.
        // Both children answer 200 on the source site, so a search engine may
        // hold either one.
        foreach ([
            '/sitemap_index.xml',
            '/wp-sitemap.xml',
            '/post-sitemap.xml',
            '/page-sitemap.xml',
        ] as $path) {
            $this->assertRedirectsTo('/sitemap.xml', $path);
        }

        $this->get('/sitemap.xml')->assertOk();
    }

    /**
     * Tag, category and author archives — and their pagination — have no
     * surface here, so they fold into the blog index rather than dead-ending.
     */
    public function test_taxonomy_and_author_archives_fold_into_the_blog(): void
    {
        foreach ([
            '/blog/tag/news/',
            '/blog/tag/how-to',
            '/blog/category/updates/',
            '/blog/category/updates/page/3/',
            '/author/jordan-example/',
            '/author/jordan-example/page/2/',
        ] as $path) {
            $this->assertRedirectsTo('/blog/', $path);
        }
    }

    /**
     * Attribution is captured on the request that lands, and this middleware
     * returns before that runs, so a dropped query loses the campaign entirely.
     */
    public function test_a_legacy_redirect_carries_its_campaign_across_the_hop(): void
    {
        $location = $this->get('/blog/page/2/?utm_source=mail&utm_campaign=spring')
            ->assertStatus(301)
            ->headers->get('Location');

        parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);

        $this->assertSame('/blog/', parse_url((string) $location, PHP_URL_PATH));
        $this->assertSame([
            'utm_campaign' => 'spring',
            'utm_source' => 'mail',
        ], collect($query)->sortKeys()->all());
    }

    /**
     * A video is not a still image, so it is excluded from the catalogue — but
     * its old URL may still be linked from elsewhere and has to resolve to the
     * copy the registry's file map declares.
     */
    public function test_media_excluded_from_the_catalogue_still_resolves(): void
    {
        $this->assertRedirectsTo(
            '/media/example/intro-video.mp4',
            '/wp-content/uploads/intro-video.mp4',
        );
    }

    /**
     * When the library is hosted elsewhere, `/media/{id}` on this host resolves
     * against a table that may be empty, so the id has to be rewritten against
     * the configured base exactly as page content is.
     */
    public function test_uploads_follow_the_library_to_another_host(): void
    {
        config()->set('media.base_url', 'https://library.example');

        $location = $this->get('/wp-content/uploads/Example-photo.png')
            ->assertStatus(301)
            ->headers->get('Location');

        $this->assertSame('https://library.example/media/42', $location);
    }

    public function test_a_current_page_is_untouched_by_the_legacy_layer(): void
    {
        $this->get('/blog/')->assertOk();
        $this->get('/sitemap.xml')->assertOk();

        // An ordinary path under a legacy prefix is left to the page fallback
        // rather than folded into the archive.
        $this->get('/blog/an-ordinary-article/')->assertNotFound();
    }

    /**
     * Resolution lands on an asset carrying that filename rather than on one
     * specific id, because a library can hold the same picture imported twice
     * under one name. Either copy serves the visitor identically.
     */
    public function test_every_original_filename_reaches_an_asset_of_that_name(): void
    {
        $index = app(MediaIndex::class);
        $unmapped = [];

        foreach ($index->assets() as $asset) {
            $resolved = $index->idForOriginalName($asset['original_name']);

            if ($resolved === null
                || strcasecmp((string) $index->find($resolved)['original_name'], $asset['original_name']) !== 0) {
                $unmapped[] = $asset['original_name'];
            }
        }

        $this->assertSame([], $unmapped, 'These library assets cannot be reached by their WordPress filename.');
    }

    private function assertRedirectsTo(string $expected, string $from): void
    {
        $response = $this->get($from);

        $this->assertSame(301, $response->getStatusCode(), $from.' should redirect.');
        $this->assertSame(
            $expected,
            parse_url((string) $response->headers->get('Location'), PHP_URL_PATH),
            $from.' redirects somewhere unexpected.',
        );
    }
}
