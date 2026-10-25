<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class RichTextBlockTest extends TestCase
{
    public function test_it_renders_every_allowed_node_as_escaped_semantic_content(): void
    {
        $html = Blade::render(
            '<x-blocks.content.rich-text id="guide" heading="Home air guide" :nodes="$nodes" />',
            ['nodes' => $this->nodes()],
        );

        $this->assertStringContainsString('<section id="guide"', $html);
        $this->assertStringContainsString('<h2 id="guide-title">Home air guide</h2>', $html);
        foreach ([3, 4, 5, 6] as $level) {
            $this->assertStringContainsString("<h{$level}", $html);
            $this->assertStringContainsString(">Level {$level}</h{$level}>", $html);
        }
        $this->assertStringContainsString('&lt;script&gt;literal text&lt;/script&gt;', $html);
        $this->assertStringContainsString('<strong>important</strong>', $html);
        $this->assertStringContainsString('<em>comfortable</em>', $html);
        $this->assertStringContainsString('<code>MERV</code>', $html);
        $this->assertStringContainsString('href="/services/air-duct-cleaning"', $html);
        $this->assertStringContainsString('href="https://example.com/source"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
        $this->assertStringContainsString('<ul', $html);
        $this->assertStringContainsString('<ol', $html);
        $this->assertStringContainsString('start="3"', $html);
        $this->assertStringContainsString('<figure', $html);
        $this->assertStringContainsString('width="1200"', $html);
        $this->assertStringContainsString('height="800"', $html);
        $this->assertStringContainsString('<figcaption>Fictional template image.</figcaption>', $html);
        foreach (['Note', 'Tip', 'Caution'] as $label) {
            $this->assertStringContainsString(">{$label}</span>", $html);
        }
        $this->assertStringContainsString('<blockquote', $html);
        $this->assertStringContainsString('Fictional reviewer', $html);
        $this->assertStringContainsString('<hr', $html);
    }

    public function test_it_omits_malformed_nodes_and_unsafe_optional_links(): void
    {
        $html = Blade::render(
            '<x-blocks.content.rich-text heading="Safe guide" :nodes="$nodes" />',
            ['nodes' => [
                ['type' => 'heading', 'level' => 1, 'text' => 'Competing H1'],
                ['type' => 'heading', 'level' => 2, 'text' => 'Competing H2'],
                ['type' => 'paragraph', 'segments' => [
                    ['text' => 'Safe literal'],
                    ['text' => 'unsafe link', 'href' => 'javascript:alert(1)'],
                ]],
                ['type' => 'image', 'src' => '/image.jpg', 'alt' => 'Missing dimensions'],
                ['type' => 'unknown', 'text' => 'Unknown node'],
            ]],
        );

        $this->assertSame(0, substr_count($html, '<h1'));
        $this->assertSame(1, substr_count($html, '<h2'));
        $this->assertStringNotContainsString('Competing H1', $html);
        $this->assertStringNotContainsString('Competing H2', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('unsafe link', $html);
        $this->assertStringNotContainsString('Missing dimensions', $html);
        $this->assertStringNotContainsString('Unknown node', $html);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function nodes(): array
    {
        return [
            ['type' => 'heading', 'level' => 3, 'text' => 'Level 3', 'id' => 'level-three'],
            ['type' => 'heading', 'level' => 4, 'text' => 'Level 4'],
            ['type' => 'heading', 'level' => 5, 'text' => 'Level 5'],
            ['type' => 'heading', 'level' => 6, 'text' => 'Level 6'],
            ['type' => 'paragraph', 'segments' => [
                ['text' => '<script>literal text</script>'],
                ['text' => ' important', 'mark' => 'strong'],
                ['text' => ' comfortable', 'mark' => 'emphasis'],
                ['text' => ' MERV', 'mark' => 'code'],
                ['text' => ' service', 'href' => '/services/air-duct-cleaning'],
                ['text' => ' source', 'href' => 'https://example.com/source'],
            ]],
            ['type' => 'unordered-list', 'items' => [
                [['text' => 'Inspect the system first.']],
                [['text' => 'Explain what was found.']],
            ]],
            ['type' => 'ordered-list', 'start' => 3, 'items' => [
                [['text' => 'Review the finished work.']],
            ]],
            [
                'type' => 'image',
                'src' => '/images/home/technician-detail.jpg',
                'alt' => 'Fictional technician inspecting equipment',
                'width' => 1200,
                'height' => 800,
                'caption' => 'Fictional template image.',
                'layout' => 'wide',
            ],
            ['type' => 'callout', 'tone' => 'note', 'title' => 'Context', 'segments' => [['text' => 'A calm note.']]],
            ['type' => 'callout', 'tone' => 'tip', 'title' => 'Practical step', 'segments' => [['text' => 'A useful tip.']]],
            ['type' => 'callout', 'tone' => 'caution', 'title' => 'Limit', 'segments' => [['text' => 'A clear caution.']]],
            [
                'type' => 'quote',
                'quote' => 'This is fictional template copy.',
                'attribution' => 'Fictional reviewer',
                'role' => 'Template content editor',
                'source_url' => 'https://example.com/source',
            ],
            ['type' => 'divider'],
        ];
    }
}
