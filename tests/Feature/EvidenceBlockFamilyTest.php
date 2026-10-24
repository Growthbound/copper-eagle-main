<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class EvidenceBlockFamilyTest extends TestCase
{
    public function test_before_after_requires_two_complete_sourced_images(): void
    {
        $html = Blade::render(
            '<x-blocks.before-after.slider heading="See the work" :before="$before" :after="$after" />',
            [
                'before' => $this->image('Before service', 'https://example.com/before'),
                'after' => $this->image('After service', 'https://example.com/after'),
            ],
        );

        $this->assertSame(2, substr_count($html, '<img'));
        $this->assertStringContainsString('type="range"', $html);
        $this->assertStringContainsString('Before service', $html);
        $this->assertStringContainsString('After service', $html);
        $this->assertSame(2, substr_count($html, 'rel="noopener noreferrer"'));

        $invalid = Blade::render(
            '<x-blocks.before-after.slider heading="See the work" :before="$before" :after="$after" />',
            [
                'before' => $this->image('Before service', 'https://example.com/before'),
                'after' => ['src' => '/after.jpg', 'alt' => 'Missing dimensions'],
            ],
        );

        $this->assertStringNotContainsString('<section', $invalid);
    }

    public function test_gallery_renders_valid_images_and_native_dialog_enhancement(): void
    {
        $html = Blade::render(
            '<x-blocks.photo-grid.gallery heading="Project gallery" :images="$images" />',
            ['images' => [
                [...$this->image('Clean return vent', 'https://example.com/photo'), 'caption' => 'Fictional project image.'],
                ['src' => '/bad.jpg', 'alt' => 'Missing dimensions'],
            ]],
        );

        $this->assertSame(2, substr_count($html, '<img'));
        $this->assertStringContainsString('width="1600"', $html);
        $this->assertStringContainsString('height="2845"', $html);
        $this->assertStringContainsString('<dialog', $html);
        $this->assertStringContainsString('data-gallery-open=', $html);
        $this->assertStringNotContainsString('Missing dimensions', $html);
    }

    public function test_case_study_labels_each_verified_narrative_stage(): void
    {
        $html = Blade::render(
            '<x-blocks.evidence.case-study heading="Fictional project" problem="Visible debris prompted a closer look." work="The agreed system sections were cleaned." outcome="The completed scope was reviewed with the homeowner." :facts="$facts" />',
            ['facts' => [
                ['label' => 'Project type', 'value' => 'Residential', 'source_label' => 'Fictional record', 'source_url' => 'https://example.com/record'],
                ['label' => 'Unsupported', 'value' => 'Claim'],
            ]],
        );

        foreach (['The situation', 'What we did', 'Documented outcome', 'Fictional record'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        $this->assertStringNotContainsString('Unsupported', $html);
    }

    public function test_video_has_native_controls_poster_dimensions_and_transcript(): void
    {
        $html = Blade::render(
            '<x-blocks.media.video heading="Watch the process" :video="$video" :poster="$poster" transcript="Fictional transcript text." />',
            [
                'video' => ['src' => 'https://example.com/video.mp4', 'type' => 'video/mp4'],
                'poster' => $this->image('Technician preparing equipment', null),
            ],
        );

        $this->assertStringContainsString('<video', $html);
        $this->assertStringContainsString('controls', $html);
        $this->assertStringContainsString('poster="/images/home/technician-detail.jpg"', $html);
        $this->assertStringContainsString('<details', $html);
        $this->assertStringContainsString('Fictional transcript text.', $html);
    }

    public function test_proof_stats_and_credentials_require_citable_sources(): void
    {
        $stats = Blade::render(
            '<x-blocks.stats.proof heading="Verified proof" :items="$items" />',
            ['items' => [
                ['value' => '42', 'label' => 'Fictional projects', 'source_label' => 'Example record', 'source_url' => 'https://example.com/stats'],
                ['value' => '99%', 'label' => 'Unsupported claim'],
            ]],
        );
        $this->assertStringContainsString('42', $stats);
        $this->assertStringContainsString('Example record', $stats);
        $this->assertStringNotContainsString('99%', $stats);

        $credentials = Blade::render(
            '<x-blocks.logo-strip.credentials heading="Documented credentials" :items="$items" />',
            ['items' => [
                [
                    'name' => 'Fictional association',
                    'image' => $this->image('Fictional association mark', null),
                    'source_label' => 'Credential record',
                    'source_url' => 'https://example.com/credential',
                ],
                ['name' => 'Unsupported badge', 'image' => $this->image('Badge', null)],
            ]],
        );
        $this->assertStringContainsString('Fictional association', $credentials);
        $this->assertStringContainsString('Credential record', $credentials);
        $this->assertStringNotContainsString('Unsupported badge', $credentials);
    }

    /** @return array<string, mixed> */
    private function image(string $alt, ?string $sourceUrl): array
    {
        return array_filter([
            'src' => '/images/home/technician-detail.jpg',
            'alt' => $alt,
            'width' => 1600,
            'height' => 2845,
            'source_label' => $sourceUrl ? 'Fictional source' : null,
            'source_url' => $sourceUrl,
        ]);
    }
}
