<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class StoryChecklistBlockTest extends TestCase
{
    public function test_it_renders_the_overlapping_media_story_and_semantic_checklist(): void
    {
        $html = Blade::render(
            <<<'BLADE'
                <x-blocks.content.story-checklist
                    id="company-story"
                    :heading="$heading"
                    :body="$body"
                    :items="$items"
                    :image="$image"
                    :inset="$inset"
                />
            BLADE,
            [
                'heading' => 'The <company> story',
                'body' => 'Template <body> copy.',
                'items' => ['Clear answers', 'Careful work', '<Respectful> visits', 'Cleaner-air goals'],
                'image' => [
                    'src' => 'images/home/technician-maintenance.jpg',
                    'alt' => 'Technician <servicing> equipment',
                    'width' => 1800,
                    'height' => 3200,
                ],
                'inset' => [
                    'src' => 'images/home/technician-detail.jpg',
                    'alt' => 'Technician inspecting equipment',
                    'width' => 1600,
                    'height' => 2845,
                ],
            ],
        );

        $this->assertStringContainsString('data-content-variant="story-checklist"', $html);
        $this->assertStringContainsString('aria-labelledby="company-story-title"', $html);
        $this->assertStringContainsString('id="company-story-title"', $html);
        $this->assertStringContainsString('The &lt;company&gt; story', $html);
        $this->assertStringContainsString('Template &lt;body&gt; copy.', $html);
        $this->assertStringContainsString('&lt;Respectful&gt; visits', $html);
        $this->assertSame(4, substr_count($html, '<li'));
        $this->assertSame(4, substr_count($html, 'story-checklist-icon'));
        $this->assertStringContainsString('/images/home/technician-maintenance.jpg', $html);
        $this->assertStringContainsString('alt="Technician &lt;servicing&gt; equipment"', $html);
        $this->assertStringContainsString('width="1800"', $html);
        $this->assertStringContainsString('height="3200"', $html);
        $this->assertStringContainsString('/images/home/technician-detail.jpg', $html);
        $this->assertStringContainsString('width="1600"', $html);
        $this->assertStringContainsString('height="2845"', $html);
        $this->assertSame(2, substr_count($html, 'loading="lazy"'));
    }

    public function test_incomplete_main_media_collapses_to_a_copy_only_layout(): void
    {
        $html = Blade::render(
            '<x-blocks.content.story-checklist heading="Company story" :image="$image" :inset="$inset" />',
            [
                'image' => [
                    'src' => 'images/home/technician-maintenance.jpg',
                    'alt' => 'Technician servicing equipment',
                ],
                'inset' => [
                    'src' => 'images/home/technician-detail.jpg',
                    'alt' => 'Technician inspecting equipment',
                    'width' => 1600,
                    'height' => 2845,
                ],
            ],
        );

        $this->assertStringContainsString('is-without-media', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('Company story', $html);
    }

    public function test_incomplete_inset_media_keeps_the_valid_main_image(): void
    {
        $html = Blade::render(
            '<x-blocks.content.story-checklist heading="Company story" :image="$image" :inset="$inset" />',
            [
                'image' => [
                    'src' => 'images/home/technician-maintenance.jpg',
                    'alt' => 'Technician servicing equipment',
                    'width' => 1800,
                    'height' => 3200,
                ],
                'inset' => [
                    'src' => 'images/home/technician-detail.jpg',
                    'alt' => 'Technician inspecting equipment',
                ],
            ],
        );

        $this->assertStringContainsString('/images/home/technician-maintenance.jpg', $html);
        $this->assertStringNotContainsString('/images/home/technician-detail.jpg', $html);
        $this->assertStringNotContainsString('story-checklist-inset', $html);
        $this->assertStringNotContainsString('is-without-media', $html);
    }

    public function test_empty_items_omit_the_checklist_cleanly(): void
    {
        $html = Blade::render(
            '<x-blocks.content.story-checklist heading="Company story" :items="[]" />',
        );

        $this->assertStringNotContainsString('story-checklist-list', $html);
        $this->assertStringNotContainsString('<ul', $html);
    }
}
