<?php

namespace App\Support\Concerns;

use App\Content\AreaRepository;
use App\Content\Enums\PageTemplate;
use App\Content\PageTemplateRegistry;

trait FictionalTemplateContent
{
    /** @return array<string, mixed> */
    protected function fixedPayload(PageTemplate $template, string $title = 'Fictional managed page'): array
    {
        $definition = app(PageTemplateRegistry::class)->definition($template);
        $sections = [];

        foreach ($definition->sectionTypes as $section => $type) {
            $sections[$section] = $this->propsForBlock($type, $section, $title);
        }

        return [
            'title' => $title,
            'description' => 'Fictional managed page description.',
            'canonical' => '/canonical/'.$template->value,
            'social_image' => $this->image('Fictional social preview'),
            'breadcrumbs' => [
                ['name' => 'Home', 'url' => '/'],
                ['name' => $title, 'url' => '/guides/fictional'],
            ],
            'sections' => $sections,
        ];
    }

    /** @return array<string, mixed> */
    protected function propsForBlock(string $type, string $id, string $title): array
    {
        $anchor = str($id)->replace('/', '-')->toString();

        return match ($type) {
            'hero/service' => [
                'id' => $anchor,
                'heading' => $title,
                'body' => 'Fictional service introduction.',
                'primaryAction' => $this->action(),
            ],
            'hero/location' => [
                'id' => $anchor,
                'heading' => $title,
                'place' => $this->governedArea()['name'] ?? 'Example Area',
                'body' => 'Fictional mobile service introduction.',
                'primaryAction' => $this->action(),
            ],
            'hero/article' => [
                'id' => $anchor,
                'heading' => $title,
                'summary' => 'Fictional educational summary.',
                'author' => 'Jordan Example',
                'publishedAt' => '2026-07-20',
                'reviewer' => ['name' => 'Taylor Example', 'reviewed_at' => '2026-07-22'],
            ],
            'stats/proof' => [
                'id' => $anchor,
                'heading' => 'Fictional sourced proof',
                'items' => [[
                    'value' => '42',
                    'label' => 'Fictional records',
                    'source_label' => 'Example source',
                    'source_url' => 'https://example.com/source',
                ]],
            ],
            'content/rich-text' => [
                'id' => $anchor,
                'heading' => str($id)->headline()->toString(),
                'nodes' => [[
                    'type' => 'paragraph',
                    'segments' => [['text' => 'Fictional structured content for this governed section.']],
                ]],
            ],
            'before-after/slider' => [
                'id' => $anchor,
                'heading' => 'Fictional documented comparison',
                'before' => $this->sourcedImage('Fictional before view'),
                'after' => $this->sourcedImage('Fictional after view'),
            ],
            'content/inclusions-exclusions' => [
                'id' => $anchor,
                'heading' => 'Fictional service scope',
                'included' => ['Approved accessible components'],
                'excluded' => ['Unapproved repairs'],
            ],
            'cta/inline-band' => [
                'id' => $anchor,
                'heading' => 'Choose a fictional next step',
                'primaryAction' => $this->action(),
            ],
            'content/direct-answer' => [
                'id' => $anchor,
                'heading' => 'What does this fictional service include?',
                'answer' => [[
                    'text' => 'This fictional service begins with a clearly explained scope, focuses on the accessible components approved for the visit, protects the surrounding work area, and ends with a review of the documented work. Exact recommendations depend on the observed system and the choices confirmed with the homeowner before service begins.',
                ]],
            ],
            'content/comparison-table' => [
                'id' => $anchor,
                'heading' => 'Fictional decision comparison',
                'caption' => 'Fictional comparison.',
                'columns' => [['key' => 'careful', 'label' => 'Careful process']],
                'rows' => [['label' => 'Scope explained', 'values' => ['careful' => 'Included']]],
            ],
            'pricing/factors' => [
                'id' => $anchor,
                'heading' => 'Fictional quote factors',
                'factors' => [['label' => 'System size', 'description' => 'The observed system shapes the scope.']],
            ],
            'evidence/case-study' => [
                'id' => $anchor,
                'heading' => 'Fictional case study',
                'problem' => 'A homeowner requested a careful inspection.',
                'work' => 'The agreed components were serviced.',
                'outcome' => 'The documented scope was reviewed.',
                'facts' => [[
                    'label' => 'Record',
                    'value' => 'Fictional',
                    'source_label' => 'Example record',
                    'source_url' => 'https://example.com/record',
                ]],
            ],
            'evidence/local-project' => [
                'id' => $anchor,
                'heading' => 'Fictional local project',
                'place' => $this->governedArea()['name'] ?? 'Example Area',
                'work' => 'The agreed components were serviced.',
                'outcome' => 'The documented scope was reviewed.',
                'source' => ['label' => 'Example project record', 'href' => 'https://example.com/project'],
            ],
            'service-areas/grid' => [
                'id' => $anchor,
                'heading' => 'Fictional service areas',
                'areas' => array_values(array_filter([$this->governedArea()])),
            ],
            'content/related-content' => [
                'id' => $anchor,
                'heading' => 'Fictional related content',
                'items' => [['title' => 'Browse services', 'href' => '/services']],
            ],
            'content/table-of-contents' => [
                'id' => $anchor,
                'heading' => 'On this fictional page',
                'items' => [['label' => 'Context', 'href' => '#context']],
            ],
            'faq/accordion' => [
                'id' => $anchor,
                'heading' => 'Fictional questions',
                'items' => [[
                    'question' => 'What should a homeowner expect?',
                    'answer' => 'A clear scope and a calm explanation of the work.',
                ]],
            ],
            'content/sources-reviewer' => [
                'id' => $anchor,
                'heading' => 'Fictional sources and reviewer',
                'sources' => [[
                    'label' => 'Example source',
                    'url' => 'https://example.com/source',
                    'reviewed_at' => '2026-07-22',
                ]],
                'reviewer' => [
                    'name' => 'Taylor Example',
                    'role' => 'Fictional reviewer',
                    'reviewed_at' => '2026-07-22',
                ],
            ],
            default => throw new \LogicException("Missing fictional fixture for {$type}."),
        };
    }

    /**
     * The first real area record, so a scaffold validates once areas exist.
     *
     * A new install has no area records yet. Area lists then start empty, and
     * blocks that need a single place fall back to a fictional one, which
     * validation flags until the editor picks a real area.
     *
     * @return array{name: string, href: string}|null
     */
    private function governedArea(): ?array
    {
        $area = app(AreaRepository::class)->all()->first();

        if ($area === null) {
            return null;
        }

        return [
            'name' => $area->name(),
            'href' => (string) (parse_url($area->url(), PHP_URL_PATH) ?: '/'),
        ];
    }

    /** @return array{label: string, href: string} */
    protected function action(): array
    {
        return ['label' => 'Request a fictional visit', 'href' => '#main-content'];
    }

    /** @return array<string, mixed> */
    protected function image(string $alt): array
    {
        return [
            'src' => '/images/home/technician-detail.jpg',
            'alt' => $alt,
            'width' => 1600,
            'height' => 2845,
        ];
    }

    /** @return array<string, mixed> */
    protected function sourcedImage(string $alt): array
    {
        return [
            ...$this->image($alt),
            'source_label' => 'Fictional project record',
            'source_url' => 'https://example.com/project-image',
        ];
    }
}
