<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EvidenceTemplatePreviewTest extends TestCase
{
    #[DataProvider('previewProvider')]
    public function test_every_editorial_and_evidence_preview_is_local_noindex_and_fictional(
        string $slug,
        string $title,
    ): void {
        $this->get('/_templates/'.$slug)
            ->assertOk()
            ->assertSee($title)
            ->assertSee('Fictional', false)
            ->assertSee('name="robots" content="noindex, nofollow"', false);

        $this->get('/templates')
            ->assertOk()
            ->assertSee('/_templates/'.$slug);
    }

    public static function previewProvider(): iterable
    {
        yield ['inclusions-exclusions', 'Inclusions and exclusions'];
        yield ['comparison-table', 'Comparison table'];
        yield ['table-of-contents', 'Table of contents'];
        yield ['sources-reviewer', 'Sources and reviewer'];
        yield ['related-content', 'Related content'];
        yield ['pricing-factors', 'Pricing factors'];
        yield ['before-after', 'Before and after'];
        yield ['photo-gallery', 'Photo gallery'];
        yield ['case-study', 'Case study'];
        yield ['video', 'Video block'];
        yield ['stats-proof', 'Proof statistics'];
        yield ['credentials', 'Credentials'];
    }
}
