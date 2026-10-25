<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class DirectAnswerBlockTest extends TestCase
{
    public function test_it_renders_only_supplied_answer_support_and_safe_actions(): void
    {
        $answer = [
            ['text' => 'Air duct cleaning removes '],
            ['text' => 'accessible debris', 'mark' => 'strong'],
            ['text' => ' from the parts of the system included in the visit.'],
        ];
        $html = Blade::render(
            <<<'BLADE'
                <x-blocks.content.direct-answer
                    id="direct-answer"
                    eyebrow="Straight answer"
                    heading="What does air duct cleaning do?"
                    :answer="$answer"
                    :supporting="$supporting"
                    :source="$source"
                    :next-step="$nextStep"
                />
            BLADE,
            [
                'answer' => $answer,
                'supporting' => [['text' => 'The exact scope depends on the system and agreed work.']],
                'source' => ['label' => 'Read the source', 'href' => 'https://example.com/source'],
                'nextStep' => ['label' => 'See the process', 'href' => '#process'],
            ],
        );

        $this->assertStringContainsString('aria-labelledby="direct-answer-title"', $html);
        $this->assertStringContainsString('<h2 id="direct-answer-title">What does air duct cleaning do?</h2>', $html);
        $this->assertStringContainsString('Air duct cleaning removes', $html);
        $this->assertStringContainsString('<strong>accessible debris</strong>', $html);
        $this->assertStringContainsString('The exact scope depends on the system and agreed work.', $html);
        $this->assertStringContainsString('href="https://example.com/source"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
        $this->assertStringContainsString('href="#process"', $html);
        $this->assertStringNotContainsString('guarantee', strtolower($html));
        $this->assertStringNotContainsString('sanitize', strtolower($html));
    }

    public function test_invalid_links_are_omitted_without_inventing_replacement_copy(): void
    {
        $html = Blade::render(
            '<x-blocks.content.direct-answer heading="Answer" :answer="$answer" :source="$source" :next-step="$nextStep" />',
            [
                'answer' => [['text' => '<Answer exactly as supplied>']],
                'source' => ['label' => 'Unsafe source', 'href' => 'javascript:alert(1)'],
                'nextStep' => ['label' => 'Unsafe step', 'href' => '//example.com'],
            ],
        );

        $this->assertStringContainsString('&lt;Answer exactly as supplied&gt;', $html);
        $this->assertStringNotContainsString('Unsafe source', $html);
        $this->assertStringNotContainsString('Unsafe step', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }
}
