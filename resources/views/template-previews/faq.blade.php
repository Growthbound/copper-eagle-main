@php
    $items = [
        [
            'question' => 'What does this highlighted FAQ template demonstrate?',
            'answer' => 'It demonstrates the open-state hierarchy using synthetic content only.',
        ],
        [
            'question' => 'Can future pages provide their own questions?',
            'answer' => 'Yes. Pages may pass approved items or use the existing FAQ repository records.',
        ],
        [
            'question' => 'Does the accordion require JavaScript?',
            'answer' => 'No. Native grouped details elements provide the single-open interaction.',
        ],
        [
            'question' => 'Will the colors follow the owner theme?',
            'answer' => 'Yes. Every visual color is supplied by the semantic theme tokens.',
        ],
        [
            'question' => 'Is this template already public?',
            'answer' => 'No. This gallery is local-only and the values are not public FAQ content.',
        ],
    ];
@endphp

<x-layouts.marketing title="Highlighted FAQ template" :noindex="true">
    <x-blocks.faq.highlighted
        id="faq-highlighted-preview"
        eyebrow="Local template preview"
        heading="Highlighted FAQ template"
        body="Template example — not public FAQ content"
        :items="$items"
    />
</x-layouts.marketing>
