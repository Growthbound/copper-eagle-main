<x-layouts.marketing title="Rich content system" :noindex="true">
    <section class="marketing-icon-catalog-heading section-space" aria-labelledby="rich-content-preview-title">
        <p class="eyebrow text-accent">Local template preview</p>
        <h1 id="rich-content-preview-title">Rich content system</h1>
        <p>Page-owned H1. The content block below owns its H2 and permits H3 through H6 only.</p>
    </section>

    <x-blocks.content.rich-text
        id="rich-content-preview"
        eyebrow="Long-form content"
        heading="Section-owned H2"
        :nodes="[
            [
                'type' => 'paragraph',
                'segments' => [
                    ['text' => 'This fictional specimen demonstrates '],
                    ['text' => 'strong text', 'mark' => 'strong'],
                    ['text' => ', '],
                    ['text' => 'emphasized text', 'mark' => 'emphasis'],
                    ['text' => ', inline '],
                    ['text' => 'MERV', 'mark' => 'code'],
                    ['text' => ', and a safe '],
                    ['text' => 'internal link', 'href' => '/services'],
                    ['text' => '.'],
                ],
            ],
            ['type' => 'heading', 'level' => 3, 'text' => 'Heading level three', 'id' => 'heading-level-three'],
            ['type' => 'paragraph', 'segments' => [
                ['text' => 'Use an H3 to divide the primary ideas inside a section. Keep each paragraph focused on one useful point and let the hierarchy explain how the ideas relate.'],
            ]],
            ['type' => 'unordered-list', 'items' => [
                [['text' => 'A short unordered list item.']],
                [['text' => 'A second item with '], ['text' => 'clear emphasis', 'mark' => 'strong'], ['text' => '.']],
                [['text' => 'A third item that remains easy to scan.']],
            ]],
            ['type' => 'heading', 'level' => 4, 'text' => 'Heading level four'],
            ['type' => 'ordered-list', 'start' => 1, 'items' => [
                [['text' => 'Start with the direct answer.']],
                [['text' => 'Explain the useful details.']],
                [['text' => 'End with a relevant next step.']],
            ]],
            [
                'type' => 'image',
                'src' => '/images/home/technician-detail.jpg',
                'alt' => 'Fictional template image of a technician inspecting home equipment',
                'width' => 1600,
                'height' => 2845,
                'caption' => 'Fictional template image with explicit dimensions and editorial caption.',
                'layout' => 'wide',
            ],
            ['type' => 'callout', 'tone' => 'note', 'title' => 'A useful note', 'segments' => [
                ['text' => 'Use a note for context that helps the reader interpret the surrounding content.'],
            ]],
            ['type' => 'heading', 'level' => 5, 'text' => 'Heading level five'],
            ['type' => 'callout', 'tone' => 'tip', 'title' => 'A practical tip', 'segments' => [
                ['text' => 'Use a tip for a safe, specific action the reader can take without overstating the outcome.'],
            ]],
            ['type' => 'heading', 'level' => 6, 'text' => 'Heading level six'],
            ['type' => 'callout', 'tone' => 'caution', 'title' => 'A clear limitation', 'segments' => [
                ['text' => 'Use caution language for limits, prerequisites, or situations that require a qualified professional.'],
            ]],
            [
                'type' => 'quote',
                'quote' => 'A useful content system makes the safest correct structure the easiest structure to publish.',
                'attribution' => 'Fictional content editor',
                'role' => 'Template specimen',
            ],
            ['type' => 'divider'],
            ['type' => 'paragraph', 'segments' => [
                ['text' => 'The divider closes one editorial thought before the next section or conversion block begins.'],
            ]],
        ]"
    />
</x-layouts.marketing>
