<?php

namespace Tests\Feature;

use App\Content\ContentValidator;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Long-form copy may carry a companion image that stays in view while the text
 * scrolls past it.
 *
 * The rail is optional and additive on purpose: 47 migrated articles render
 * through this same block and none of them pass media, so the reading column
 * has to be untouched when none is given.
 */
class RichTextRailTest extends TestCase
{
    public function test_it_renders_a_rail_when_media_is_supplied(): void
    {
        $html = $this->render([
            ['src' => '/media/24', 'alt' => 'Ductwork running along a basement ceiling', 'width' => 1920, 'height' => 700],
        ]);

        $this->assertStringContainsString('rich-content-rail', $html);
        $this->assertStringContainsString('has-rail', $html);
        $this->assertStringContainsString('alt="Ductwork running along a basement ceiling"', $html);
    }

    public function test_the_reading_column_is_unchanged_without_media(): void
    {
        $html = $this->render();

        $this->assertStringNotContainsString('rich-content-rail', $html);
        $this->assertStringNotContainsString('has-rail', $html);
        $this->assertStringContainsString('rich-content-reading', $html);
    }

    public function test_a_caption_renders_beneath_its_image(): void
    {
        $html = $this->render([[
            'src' => '/media/24',
            'alt' => 'Ductwork running along a basement ceiling',
            'width' => 1920,
            'height' => 700,
            'caption' => 'Photographed on one of our own jobs.',
        ]]);

        $this->assertStringContainsString('Photographed on one of our own jobs.', $html);
    }

    public function test_the_rail_is_capped_so_it_cannot_outrun_the_copy(): void
    {
        $image = fn (int $id): array => [
            'src' => '/media/'.$id, 'alt' => 'A duct run after cleaning', 'width' => 800, 'height' => 1000,
        ];

        $html = $this->render([$image(24), $image(30), $image(18), $image(22)]);

        $this->assertSame(2, substr_count($html, '<img'));
    }

    public function test_rail_media_is_validated_like_every_other_image(): void
    {
        $result = app(ContentValidator::class)->validate(
            ['kind' => 'page', 'slug' => 'rail-fixture', 'title' => 'Fixture', 'template' => 'standard'],
            [
                'title' => 'Fixture',
                'description' => 'Exercises the rich-text media contract.',
                'blocks' => [[
                    'type' => 'content/rich-text',
                    'props' => [
                        'id' => 'rich-content',
                        'heading' => 'A heading',
                        'nodes' => [['type' => 'paragraph', 'segments' => [['text' => 'Body copy.']]]],
                        'media' => [['src' => '/media/24', 'alt' => 'Missing its dimensions']],
                    ],
                ]],
            ],
        );

        $this->assertFalse($result->isValid(), 'An image with no dimensions must not validate.');
    }

    /**
     * @param  list<array<string, mixed>>  $media
     */
    private function render(array $media = []): string
    {
        return Blade::render(
            '<x-blocks.content.rich-text heading="A heading" :nodes="$nodes" :media="$media" />',
            [
                'nodes' => [['type' => 'paragraph', 'segments' => [['text' => 'Body copy for the fixture.']]]],
                'media' => $media,
            ],
        );
    }
}
