<?php

namespace Tests\Unit;

use App\Content\MediaIndex;
use PHPUnit\Framework\TestCase;

class MediaIndexTest extends TestCase
{
    public function test_it_accepts_an_analyzed_asset_and_a_catalog_only_asset(): void
    {
        $index = $this->index([
            $this->asset(43, analyzed: true),
            [
                'id' => 44,
                'path' => '/media/44',
                'original_name' => 'project-before.jpg',
                'width' => 802,
                'height' => 802,
                'provenance' => 'first_party',
                'evidence_class' => 'before_after',
                'verified' => 'catalog_only',
                'subject' => [],
                'description' => null,
                'alt' => null,
            ],
        ]);

        $this->assertSame([], (new MediaIndex)->validate($index));
    }

    public function test_it_rejects_duplicate_ids(): void
    {
        $index = $this->index([$this->asset(43), $this->asset(43)]);

        $this->assertStringContainsString(
            'appears more than once',
            implode("\n", (new MediaIndex)->validate($index)),
        );
    }

    public function test_it_requires_the_path_to_match_the_id(): void
    {
        $asset = $this->asset(43);
        $asset['path'] = '/media/99';

        $this->assertStringContainsString(
            'path must be /media/43',
            implode("\n", (new MediaIndex)->validate($this->index([$asset]))),
        );
    }

    public function test_it_rejects_an_unknown_provenance(): void
    {
        $asset = $this->asset(43);
        $asset['provenance'] = 'probably-fine';

        $this->assertStringContainsString(
            'provenance must be one of',
            implode("\n", (new MediaIndex)->validate($this->index([$asset]))),
        );
    }

    public function test_an_analyzed_asset_must_carry_alt_text_a_description_and_subjects(): void
    {
        foreach (['alt', 'description', 'subject'] as $field) {
            $asset = $this->asset(43, analyzed: true);
            $asset[$field] = $field === 'subject' ? [] : null;

            $this->assertStringContainsString(
                "{$field} is required once an asset is analyzed",
                implode("\n", (new MediaIndex)->validate($this->index([$asset]))),
                "Expected {$field} to be required for an analyzed asset.",
            );
        }
    }

    public function test_a_catalog_only_asset_must_not_claim_alt_text_it_cannot_have_verified(): void
    {
        $asset = $this->asset(43);
        $asset['verified'] = 'catalog_only';
        $asset['alt'] = 'A confident description of an image nobody looked at';

        $this->assertStringContainsString(
            'must not carry alt text or a description until it is analyzed',
            implode("\n", (new MediaIndex)->validate($this->index([$asset]))),
        );
    }

    public function test_stock_media_may_not_be_classed_as_proof(): void
    {
        $asset = $this->asset(43, analyzed: true);
        $asset['provenance'] = 'stock';
        $asset['evidence_class'] = 'job_documentation';

        $this->assertStringContainsString(
            'which only first-party media may claim',
            implode("\n", (new MediaIndex)->validate($this->index([$asset]))),
        );
    }

    public function test_a_location_photo_may_come_from_anywhere(): void
    {
        $asset = $this->asset(43, analyzed: true);
        $asset['provenance'] = 'unknown';
        $asset['evidence_class'] = 'location';

        $this->assertSame([], (new MediaIndex)->validate($this->index([$asset])));
    }

    public function test_it_rejects_non_positive_dimensions(): void
    {
        $asset = $this->asset(43);
        $asset['width'] = 0;

        $this->assertStringContainsString(
            'width and height must be positive',
            implode("\n", (new MediaIndex)->validate($this->index([$asset]))),
        );
    }

    public function test_it_rejects_a_duplicate_reference_to_a_missing_asset(): void
    {
        $asset = $this->asset(43, analyzed: true);
        $asset['duplicate_of'] = 999;

        $this->assertStringContainsString(
            'duplicate_of 999, which is not in the index',
            implode("\n", (new MediaIndex)->validate($this->index([$asset]))),
        );
    }

    public function test_it_requires_confirmed_rights_for_the_library(): void
    {
        $index = $this->index([$this->asset(43)]);
        unset($index['rights']);

        $this->assertStringContainsString(
            'rights must record a holder, a status and a confirmation date',
            implode("\n", (new MediaIndex)->validate($index)),
        );
    }

    public function test_it_resolves_a_media_path_against_the_configured_base(): void
    {
        $this->assertSame(
            'https://example.test/media/43',
            MediaIndex::resolve('/media/43', 'https://example.test'),
        );

        $this->assertSame(
            'https://example.test/media/43',
            MediaIndex::resolve('/media/43', 'https://example.test/'),
        );
    }

    public function test_it_leaves_paths_alone_when_no_base_is_configured(): void
    {
        $this->assertSame('/media/43', MediaIndex::resolve('/media/43', null));
        $this->assertSame('/media/43', MediaIndex::resolve('/media/43', ''));
    }

    public function test_it_only_rewrites_media_paths(): void
    {
        $this->assertSame(
            '/build/assets/app.css',
            MediaIndex::resolve('/build/assets/app.css', 'https://example.test'),
        );

        $this->assertSame(
            'https://cdn.example.com/a.jpg',
            MediaIndex::resolve('https://cdn.example.com/a.jpg', 'https://example.test'),
        );
    }

    public function test_repository_files_are_not_pointed_at_the_remote_library(): void
    {
        // /media/{brand}/* are files committed to this repository. The library
        // host has never heard of them, so rewriting them would break every
        // image that currently works.
        $this->assertSame(
            '/media/example/finished-project.jpg',
            MediaIndex::resolve('/media/example/finished-project.jpg', 'https://example.test'),
        );

        $this->assertSame(
            '/media/example/intro-video.mp4',
            MediaIndex::resolve('/media/example/intro-video.mp4', 'https://example.test'),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $assets
     * @return array<string, mixed>
     */
    private function index(array $assets): array
    {
        return [
            'schema_version' => 1,
            'source_origin' => 'https://library.example.test',
            'reviewed_on' => '2026-08-01',
            'rights' => [
                'holder' => 'Example Co.',
                'status' => 'approved',
                'confirmed_on' => '2026-08-01',
                'note' => 'Owner confirmed ownership or licence for web use.',
            ],
            'assets' => $assets,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function asset(int $id, bool $analyzed = false): array
    {
        return [
            'id' => $id,
            'path' => '/media/'.$id,
            'original_name' => 'Project-after.jpg',
            'width' => 601,
            'height' => 802,
            'provenance' => 'first_party',
            'evidence_class' => 'before_after',
            'verified' => $analyzed ? 'analyzed' : 'catalog_only',
            'subject' => $analyzed ? ['finished-project', 'after'] : [],
            'description' => $analyzed ? 'A finished project photographed on site after the work was done.' : null,
            'alt' => $analyzed ? 'A finished project photographed on site' : null,
        ];
    }
}
