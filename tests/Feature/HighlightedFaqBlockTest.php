<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class HighlightedFaqBlockTest extends TestCase
{
    public function test_it_renders_a_single_group_with_the_first_item_open(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('data-faq-variant="highlighted"', $html);
        $this->assertSame(3, substr_count($html, 'name="faq-example-group"'));
        $this->assertSame(1, preg_match_all('/<details[^>]*\sopen(?:\s|>)/', $html));
        $this->assertMatchesRegularExpression('/<details[^>]*\sopen(?:\s|>).*?First example question/s', $html);
    }

    public function test_a_custom_open_index_opens_exactly_that_item(): void
    {
        $html = $this->render(openIndex: 1);

        $this->assertSame(1, preg_match_all('/<details[^>]*\sopen(?:\s|>)/', $html));
        $this->assertMatchesRegularExpression('/<details[^>]*\sopen(?:\s|>).*?Second example question/s', $html);
    }

    public function test_a_null_open_index_leaves_every_item_closed(): void
    {
        $html = Blade::render(
            '<x-blocks.faq.highlighted id="faq-example" heading="Example FAQs" :items="$items" :open-index="null" />',
            ['items' => $this->items()],
        );

        $this->assertSame(0, preg_match_all('/<details[^>]*\sopen(?:\s|>)/', $html));
    }

    public function test_empty_items_render_no_section(): void
    {
        $html = Blade::render(
            '<x-blocks.faq.highlighted heading="Example FAQs" :items="[]" />',
        );

        $this->assertSame('', trim($html));
    }

    public function test_copy_is_escaped_and_optional_body_omits_cleanly(): void
    {
        $items = [['question' => '<Question>', 'answer' => '<Answer>']];

        $html = Blade::render(
            '<x-blocks.faq.highlighted :heading="$heading" :items="$items" />',
            ['heading' => '<Heading>', 'items' => $items],
        );

        $this->assertStringContainsString('&lt;Heading&gt;', $html);
        $this->assertStringContainsString('&lt;Question&gt;', $html);
        $this->assertStringContainsString('&lt;Answer&gt;', $html);
        $this->assertStringNotContainsString('faq-highlighted-intro', $html);
    }

    public function test_the_existing_accordion_variant_still_renders(): void
    {
        $html = Blade::render(
            '<x-blocks.faq.accordion heading="Existing variant" :items="$items" />',
            ['items' => $this->items()],
        );

        $this->assertStringContainsString('Existing variant', $html);
        $this->assertStringContainsString('First example question', $html);
    }

    /** @return array<int, array{question: string, answer: string}> */
    private function items(): array
    {
        return [
            ['question' => 'First example question', 'answer' => 'First example answer.'],
            ['question' => 'Second example question', 'answer' => 'Second example answer.'],
            ['question' => 'Third example question', 'answer' => 'Third example answer.'],
        ];
    }

    private function render(int $openIndex = 0): string
    {
        return Blade::render(
            '<x-blocks.faq.highlighted id="faq-example" heading="Example FAQs" :items="$items" :open-index="$openIndex" />',
            ['items' => $this->items(), 'openIndex' => $openIndex],
        );
    }
}
