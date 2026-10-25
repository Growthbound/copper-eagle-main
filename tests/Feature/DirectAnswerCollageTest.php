<?php

namespace Tests\Feature;

use App\Content\ContentValidator;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The direct-answer block may carry supporting imagery.
 *
 * A long answer with nothing beside it reads as a wall of text, which is the
 * opposite of what an answer-first block is for. The images are optional, and
 * every one still has to satisfy the same media contract as anywhere else, so
 * an unverified image cannot slip in through a new prop.
 */
class DirectAnswerCollageTest extends TestCase
{
    public function test_it_renders_supporting_images_beside_the_answer(): void
    {
        $html = Blade::render(
            '<x-blocks.content.direct-answer heading="What happens" :answer="$answer" :media="$media" />',
            [
                'answer' => [['text' => 'A clear answer to one question.']],
                'media' => [
                    ['src' => '/media/22', 'alt' => 'Technician setting up equipment', 'width' => 1421, 'height' => 799],
                    ['src' => '/media/26', 'alt' => 'Technician inspecting the work with a camera', 'width' => 1086, 'height' => 616],
                ],
            ],
        );

        $this->assertStringContainsString('direct-answer-collage', $html);
        $this->assertStringContainsString('has-collage', $html);
        $this->assertStringContainsString('alt="Technician setting up equipment"', $html);
    }

    public function test_the_layout_stays_single_column_without_images(): void
    {
        $html = Blade::render(
            '<x-blocks.content.direct-answer heading="What happens" :answer="$answer" />',
            ['answer' => [['text' => 'A clear answer to one question.']]],
        );

        $this->assertStringNotContainsString('direct-answer-collage', $html);
        $this->assertStringNotContainsString('has-collage', $html);
    }

    public function test_an_image_without_alt_text_is_dropped_rather_than_rendered(): void
    {
        $html = Blade::render(
            '<x-blocks.content.direct-answer heading="What happens" :answer="$answer" :media="$media" />',
            [
                'answer' => [['text' => 'A clear answer to one question.']],
                'media' => [['src' => '/media/22', 'alt' => '', 'width' => 1421, 'height' => 799]],
            ],
        );

        $this->assertStringNotContainsString('direct-answer-collage', $html);
    }

    public function test_the_collage_is_capped_so_it_cannot_overwhelm_the_answer(): void
    {
        $image = fn (int $id): array => [
            'src' => '/media/'.$id, 'alt' => 'A technician at work', 'width' => 800, 'height' => 600,
        ];

        $html = Blade::render(
            '<x-blocks.content.direct-answer heading="What happens" :answer="$answer" :media="$media" />',
            [
                'answer' => [['text' => 'A clear answer to one question.']],
                'media' => [$image(22), $image(26), $image(14), $image(15), $image(18)],
            ],
        );

        $this->assertSame(3, substr_count($html, '<img'));
    }

    public function test_the_imagery_can_sit_on_either_side_of_the_answer(): void
    {
        $media = [['src' => '/media/22', 'alt' => 'A technician at work', 'width' => 1421, 'height' => 799]];

        $default = Blade::render(
            '<x-blocks.content.direct-answer heading="What happens" :answer="$answer" :media="$media" />',
            ['answer' => [['text' => 'An answer.']], 'media' => $media],
        );

        $mirrored = Blade::render(
            '<x-blocks.content.direct-answer heading="What happens" :answer="$answer" :media="$media" media-side="end" />',
            ['answer' => [['text' => 'An answer.']], 'media' => $media],
        );

        $this->assertStringNotContainsString('media-end', $default);
        $this->assertStringContainsString('media-end', $mirrored);
    }

    public function test_the_side_is_ignored_when_there_is_no_imagery(): void
    {
        $html = Blade::render(
            '<x-blocks.content.direct-answer heading="What happens" :answer="$answer" media-side="end" />',
            ['answer' => [['text' => 'An answer.']]],
        );

        $this->assertStringNotContainsString('media-end', $html);
    }

    public function test_the_media_prop_is_validated_like_every_other_image(): void
    {
        $result = app(ContentValidator::class)->validate(
            ['kind' => 'page', 'slug' => 'collage-fixture', 'title' => 'Fixture', 'template' => 'standard'],
            [
                'title' => 'Fixture',
                'description' => 'Exercises the direct-answer media contract.',
                'blocks' => [[
                    'type' => 'content/direct-answer',
                    'props' => [
                        'id' => 'direct-answer',
                        'heading' => 'What happens',
                        'answer' => [['text' => 'A clear answer.']],
                        'media' => [['src' => '/media/22', 'alt' => 'A technician at work']],
                    ],
                ]],
            ],
        );

        $this->assertFalse($result->isValid(), 'An image with no dimensions must not validate.');
    }
}
