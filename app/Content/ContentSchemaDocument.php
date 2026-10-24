<?php

namespace App\Content;

use App\Content\Enums\ApiKeyScope;
use App\Content\Enums\ContentKind;
use App\Content\Enums\ContentSource;
use App\Content\Enums\ContentStatus;
use App\Content\Enums\RichNodeType;

final readonly class ContentSchemaDocument
{
    public function __construct(
        private BlockRegistry $blocks,
        private PageTemplateRegistry $templates,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schema_version' => 1,
            'blocks' => array_map(
                fn ($definition): array => $definition->toArray(),
                $this->blocks->all(),
            ),
            'page_templates' => array_map(
                fn ($definition): array => $definition->toArray(),
                $this->templates->all(),
            ),
            'rich_content' => [
                'node_types' => array_column(RichNodeType::cases(), 'value'),
                'heading_levels' => [3, 4, 5, 6],
                'marks' => RichContent::MARKS,
                'image_layouts' => RichContent::IMAGE_LAYOUTS,
                'callout_tones' => RichContent::CALLOUT_TONES,
                'safe_url_forms' => [
                    'root-relative',
                    'fragment',
                    'mailto',
                    'tel',
                    'http',
                    'https',
                ],
                'direct_answer_words' => [
                    'minimum' => 40,
                    'maximum' => 70,
                ],
                'inline_segments' => [
                    'required' => ['text'],
                    'optional' => ['mark', 'href'],
                    'allowed_marks' => RichContent::MARKS,
                ],
                'nodes' => $this->richNodeSchema(),
                'examples' => [
                    [
                        'type' => 'paragraph',
                        'segments' => [
                            ['text' => 'A normal sentence with '],
                            ['text' => 'important language', 'mark' => 'strong'],
                            ['text' => ' and a source', 'href' => '/resources/example'],
                        ],
                    ],
                    [
                        'type' => 'callout',
                        'tone' => 'tip',
                        'title' => 'A practical next step',
                        'segments' => [
                            ['text' => 'Ask what the visit includes before booking.'],
                        ],
                    ],
                ],
                'safe_url_rules' => [
                    'managed_html' => 'No raw HTML is accepted.',
                    'protocol_relative' => 'Protocol-relative URLs are rejected.',
                    'control_characters' => 'URLs containing control characters are rejected.',
                    'external_rel' => 'External links render with noopener noreferrer.',
                ],
                'rules' => [
                    'h1_owner' => 'page',
                    'h2_owner' => 'block',
                    'managed_html' => false,
                    'invalid_nodes' => 'omit',
                ],
            ],
            'enums' => [
                'content_kinds' => array_column(ContentKind::supportedCases(), 'value'),
                'content_statuses' => array_column(ContentStatus::cases(), 'value'),
                'content_sources' => array_column(ContentSource::cases(), 'value'),
                'api_key_scopes' => array_column(ApiKeyScope::cases(), 'value'),
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function richNodeSchema(): array
    {
        return [
            'heading' => [
                'required' => ['level', 'text'],
                'optional' => ['id'],
                'allowed_levels' => [3, 4, 5, 6],
            ],
            'paragraph' => [
                'required' => ['segments'],
                'optional' => [],
            ],
            'unordered-list' => [
                'required' => ['items'],
                'optional' => [],
            ],
            'ordered-list' => [
                'required' => ['items'],
                'optional' => ['start'],
            ],
            'image' => [
                'required' => ['media_id_or_src', 'alt', 'width', 'height'],
                'optional' => ['caption', 'layout'],
            ],
            'callout' => [
                'required' => ['tone', 'title', 'segments'],
                'optional' => [],
            ],
            'quote' => [
                'required' => ['quote'],
                'optional' => ['attribution', 'role', 'source_url'],
            ],
            'divider' => [
                'required' => [],
                'optional' => [],
            ],
        ];
    }
}
