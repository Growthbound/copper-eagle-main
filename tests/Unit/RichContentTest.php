<?php

namespace Tests\Unit;

use App\Content\BlockRegistry;
use App\Content\ContentSchemaDocument;
use App\Content\Enums\RichNodeType;
use App\Content\RichContent;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RichContentTest extends TestCase
{
    public function test_it_normalizes_safe_segments_and_omits_invalid_nodes(): void
    {
        $rich = app(RichContent::class);

        $nodes = $rich->normalizeNodes([
            ['type' => 'heading', 'level' => 3, 'text' => 'What we found'],
            ['type' => 'paragraph', 'segments' => [
                ['text' => '<script>'],
                ['text' => 'source', 'href' => 'javascript:alert(1)'],
            ]],
            ['type' => 'heading', 'level' => 1, 'text' => 'Second H1'],
        ]);

        $this->assertCount(2, $nodes);
        $this->assertSame('<script>', $nodes[1]['segments'][0]['text']);
        $this->assertArrayNotHasKey('href', $nodes[1]['segments'][1]);
        $this->assertFalse($rich->validateNodes($nodes)->hasErrors());
        $this->assertTrue($rich->validateNodes([
            ['type' => 'heading', 'level' => 1, 'text' => 'Bad'],
        ])->hasErrors());
    }

    #[DataProvider('safeUrlProvider')]
    public function test_it_accepts_only_the_documented_safe_url_forms(string $url): void
    {
        $this->assertTrue(app(RichContent::class)->isSafeUrl($url));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function safeUrlProvider(): array
    {
        return [
            'root relative' => ['/services/air-duct-cleaning'],
            'fragment' => ['#pricing'],
            'email' => ['mailto:hello@example.com'],
            'phone' => ['tel:+1-555-555-0100'],
            'http' => ['http://example.com/resource'],
            'https' => ['https://example.com/resource?q=air'],
        ];
    }

    #[DataProvider('unsafeUrlProvider')]
    public function test_it_rejects_unsafe_or_ambiguous_urls(mixed $url): void
    {
        $this->assertFalse(app(RichContent::class)->isSafeUrl($url));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unsafeUrlProvider(): array
    {
        return [
            'protocol relative' => ['//example.com'],
            'control character' => ["/services/\nadmin"],
            'data' => ['data:text/html,hello'],
            'file' => ['file:///etc/passwd'],
            'javascript' => ['javascript:alert(1)'],
            'backslash ambiguity' => ['/services\\admin'],
            'empty' => [''],
            'not a string' => [17],
        ];
    }

    public function test_it_validates_every_structured_node_with_json_pointers(): void
    {
        $result = app(RichContent::class)->validateNodes([
            ['type' => 'heading', 'level' => 2, 'text' => 'Owned by the page'],
            ['type' => 'unordered-list', 'items' => []],
            ['type' => 'image', 'src' => '/media/example.jpg', 'alt' => 'Supply vent', 'width' => 0, 'height' => 900],
            ['type' => 'callout', 'tone' => 'alarm', 'title' => 'Note', 'segments' => [['text' => 'Calm context.']]],
            ['type' => 'quote', 'quote' => 'Quoted text.', 'source_url' => 'data:text/plain,bad'],
        ]);

        $this->assertSame([
            '/nodes/0/level',
            '/nodes/1/items',
            '/nodes/2/width',
            '/nodes/3/tone',
            '/nodes/4/source_url',
        ], array_keys($result->errors()));
    }

    public function test_rich_content_headings_start_at_h3_and_do_not_skip_levels(): void
    {
        $rich = app(RichContent::class);

        $firstLevelSkipped = $rich->validateNodes([
            ['type' => 'heading', 'level' => 4, 'text' => 'Starts too deep'],
        ]);
        $nestedLevelSkipped = $rich->validateNodes([
            ['type' => 'heading', 'level' => 3, 'text' => 'Section'],
            ['type' => 'heading', 'level' => 5, 'text' => 'Skips H4'],
        ]);

        $this->assertArrayHasKey('/nodes/0/level', $firstLevelSkipped->errors());
        $this->assertArrayHasKey('/nodes/1/level', $nestedLevelSkipped->errors());
    }

    public function test_it_normalizes_all_supported_node_shapes_without_generating_html(): void
    {
        $nodes = app(RichContent::class)->normalizeNodes([
            ['type' => 'unordered-list', 'items' => [[['text' => 'First']]]],
            ['type' => 'ordered-list', 'start' => 3, 'items' => [[['text' => 'Third', 'mark' => 'strong']]]],
            ['type' => 'image', 'media_id' => 9, 'alt' => 'Clean return vent', 'width' => 1200, 'height' => 800, 'caption' => 'After cleaning', 'layout' => 'wide'],
            ['type' => 'callout', 'tone' => 'tip', 'title' => 'Helpful tip', 'segments' => [['text' => 'Change filters on schedule.']]],
            ['type' => 'quote', 'quote' => 'They explained the process.', 'attribution' => 'Verified customer', 'source_url' => 'https://example.com/review'],
            ['type' => 'divider'],
        ]);

        $this->assertSame([
            'unordered-list',
            'ordered-list',
            'image',
            'callout',
            'quote',
            'divider',
        ], array_column($nodes, 'type'));
        $this->assertSame(3, $nodes[1]['start']);
        $this->assertSame('strong', $nodes[1]['items'][0][0]['mark']);
        $this->assertSame(9, $nodes[2]['media_id']);
        $this->assertSame('wide', $nodes[2]['layout']);
        $this->assertStringNotContainsString('<', json_encode($nodes, JSON_THROW_ON_ERROR));
    }

    public function test_direct_answer_warns_outside_the_editorial_word_range_without_rewriting_copy(): void
    {
        $rich = app(RichContent::class);
        $short = [['text' => implode(' ', array_fill(0, 39, 'word'))]];
        $minimum = [['text' => implode(' ', array_fill(0, 40, 'word'))]];
        $maximum = [['text' => implode(' ', array_fill(0, 70, 'word'))]];
        $long = [['text' => implode(' ', array_fill(0, 71, 'word'))]];

        $this->assertArrayHasKey('/answer', $rich->validateDirectAnswer($short)->warnings());
        $this->assertSame([], $rich->validateDirectAnswer($minimum)->warnings());
        $this->assertSame([], $rich->validateDirectAnswer($maximum)->warnings());
        $this->assertArrayHasKey('/answer', $rich->validateDirectAnswer($long)->warnings());
        $this->assertSame($short, $rich->normalizeSegments($short));
    }

    public function test_required_inline_segments_reject_whitespace_only_text(): void
    {
        $rich = app(RichContent::class);

        $paragraph = $rich->validateNodes([[
            'type' => 'paragraph',
            'segments' => [['text' => " \n\t "]],
        ]]);
        $directAnswer = $rich->validateDirectAnswer([
            ['text' => '   '],
        ]);

        $this->assertArrayHasKey('/nodes/0/segments/0/text', $paragraph->errors());
        $this->assertArrayHasKey('/answer/0/text', $directAnswer->errors());
    }

    public function test_quote_optional_metadata_must_be_text(): void
    {
        $result = app(RichContent::class)->validateNodes([[
            'type' => 'quote',
            'quote' => 'A valid quote body.',
            'attribution' => ['not' => 'text'],
            'role' => (object) ['not' => 'text'],
        ]]);

        $this->assertArrayHasKey('/nodes/0/attribution', $result->errors());
        $this->assertArrayHasKey('/nodes/0/role', $result->errors());
    }

    public function test_schema_document_exposes_the_same_rich_content_rules_agents_must_follow(): void
    {
        $schema = app(ContentSchemaDocument::class)->toArray()['rich_content'];

        $this->assertSame(
            array_column(RichNodeType::cases(), 'value'),
            $schema['node_types'],
        );
        $this->assertSame([3, 4, 5, 6], $schema['heading_levels']);
        $this->assertSame(['plain', 'strong', 'emphasis', 'code'], $schema['marks']);
        $this->assertSame(['inline', 'wide'], $schema['image_layouts']);
        $this->assertSame(['note', 'tip', 'caution'], $schema['callout_tones']);
        $this->assertSame(['root-relative', 'fragment', 'mailto', 'tel', 'http', 'https'], $schema['safe_url_forms']);
        $this->assertSame(['minimum' => 40, 'maximum' => 70], $schema['direct_answer_words']);
        $this->assertSame(['text'], $schema['inline_segments']['required']);
        $this->assertSame(['mark', 'href'], $schema['inline_segments']['optional']);
        $this->assertSame(['level', 'text'], $schema['nodes']['heading']['required']);
        $this->assertSame(['media_id_or_src', 'alt', 'width', 'height'], $schema['nodes']['image']['required']);
        $this->assertSame('paragraph', $schema['examples'][0]['type']);
        $this->assertSame('No raw HTML is accepted.', $schema['safe_url_rules']['managed_html']);
    }

    public function test_block_registry_uses_the_shared_rich_content_validator(): void
    {
        $blocks = app(BlockRegistry::class);

        $richText = $blocks->validate('content/rich-text', [
            'heading' => 'A useful guide',
            'nodes' => [
                ['type' => 'heading', 'level' => 1, 'text' => 'Competing H1'],
            ],
        ]);
        $directAnswer = $blocks->validate('content/direct-answer', [
            'heading' => 'What does duct cleaning do?',
            'answer' => [['text' => implode(' ', array_fill(0, 39, 'word'))]],
        ]);

        $this->assertArrayHasKey('/props/nodes/0/level', $richText->errors());
        $this->assertArrayHasKey('/props/answer', $directAnswer->warnings());
    }
}
