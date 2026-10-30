<?php

namespace Tests\Feature;

use App\Content\ContentDraftScaffold;
use App\Content\ContentValidator;
use App\Content\Enums\ContentKind;
use App\Content\Enums\PageTemplate;
use App\Content\PageTemplateRegistry;
use Tests\TestCase;

/**
 * The Copper Eagle page templates are the drift guard for content: an agent
 * picks one and fills props, and the section order and block types stay put.
 * These tests hold the registry to the table in docs/brand/design-system.md
 * and prove each template renders as one complete, well-formed page.
 */
class CopperEaglePageTemplatesTest extends TestCase
{
    /** @var array<string, string> template => gallery preview slug */
    private const PREVIEWS = [
        'home' => 'home-page',
        'services-hub' => 'services-hub-page',
        'service-detail' => 'service-detail-page',
        'emergency' => 'emergency-page',
        'about' => 'about-page',
        'team' => 'team-page',
        'reviews' => 'reviews-page',
        'contact' => 'contact-page',
        'city' => 'city-page',
    ];

    public function test_the_registry_matches_the_design_spec_section_for_section(): void
    {
        $spec = $this->specTemplates();

        $this->assertSame(array_keys(self::PREVIEWS), array_keys($spec), 'The page template table in the design spec lists different templates.');

        foreach ($spec as $template => ['blocks' => $blocks, 'optional' => $optional]) {
            $definition = app(PageTemplateRegistry::class)->definition(PageTemplate::from($template));

            $this->assertSame(
                $blocks,
                array_values($definition->sectionTypes),
                "The {$template} template's blocks differ from docs/brand/design-system.md.",
            );

            $optionalSections = array_values(array_diff($definition->sequence, $definition->requiredSections));

            $this->assertSame(
                $optional,
                array_map(fn (string $section): string => $definition->sectionTypes[$section], $optionalSections),
                "Every {$template} section is required unless docs/brand/design-system.md marks it optional.",
            );
        }
    }

    public function test_each_template_renders_one_complete_page(): void
    {
        foreach (self::PREVIEWS as $template => $slug) {
            $html = $this->get('/_templates/'.$slug)->assertOk()->getContent();
            $definition = app(PageTemplateRegistry::class)->definition(PageTemplate::from($template));

            $this->assertSame(1, substr_count($html, '<h1'), "The {$template} template must render exactly one H1.");

            preg_match_all('/data-page-section="([^"]+)"/', $html, $sections);
            $this->assertSame($definition->sequence, $sections[1], "The {$template} sections rendered out of order.");

            // The hero renders the header first, so it sits inside the first
            // section's wrapper but ahead of the H1 and every later section.
            $header = strpos($html, 'class="site-header');
            $heading = strpos($html, '<h1');
            $secondSection = strpos($html, 'data-page-section="'.$definition->sequence[1].'"');
            $lastSection = strrpos($html, 'data-page-section=');
            $footer = strpos($html, 'class="site-footer');

            $this->assertNotFalse($header, "The {$template} page has no site header.");
            $this->assertNotFalse($footer, "The {$template} page has no site footer.");
            $this->assertLessThan($heading, $header, "The {$template} header must come before the H1.");
            $this->assertLessThan($secondSection, $header, "The {$template} header must come before the content.");
            $this->assertGreaterThan($lastSection, $footer, "The {$template} footer must come after the content.");
            $this->assertStringContainsString('name="robots" content="noindex', $html, "The {$template} preview must stay out of search.");
        }
    }

    public function test_a_new_draft_from_each_template_is_complete_but_held_back(): void
    {
        foreach (array_keys(self::PREVIEWS) as $template) {
            $template = PageTemplate::from($template);
            $payload = app(ContentDraftScaffold::class)->for($template, 'Draft page');
            $definition = app(PageTemplateRegistry::class)->definition($template);

            $this->assertSame($definition->sequence, array_keys($payload['sections']));
            $this->assertTrue($payload['noindex']);
            $this->assertTrue($payload['_scaffold']);

            $errors = app(ContentValidator::class)->validate(
                ['kind' => ContentKind::Page, 'slug' => 'draft-'.$template->value, 'title' => 'Draft page', 'template' => $template],
                $payload,
            )->errors();

            $this->assertArrayHasKey('/_scaffold', $errors, "A {$template->value} scaffold must not be publishable until it is replaced.");
        }
    }

    public function test_a_service_page_can_carry_six_cards_labelled_points_and_paragraphs(): void
    {
        $payload = app(ContentDraftScaffold::class)->for(PageTemplate::ServiceDetail, 'Sample service');
        unset($payload['_scaffold']);

        $card = $payload['sections']['offerings']['items'][0];
        $payload['sections']['offerings']['items'] = array_map(
            fn (int $index): array => ['title' => "Card {$index}", 'body' => "First paragraph {$index}.\n\nSecond paragraph."] + $card,
            range(1, 6),
        );
        $payload['sections']['introduction']['body'] = "One.\n\nTwo.";
        $payload['sections']['introduction']['checklist'] = [
            ['label' => 'Sample label', 'text' => 'Sample text under the label.'],
            'A plain point',
        ];
        $payload['sections']['guarantee']['body'] = "One.\n\nTwo.";
        $payload['sections']['faq']['items'][0]['answer'] = "One.\n\nTwo.";

        $errors = app(ContentValidator::class)->validate(
            ['kind' => ContentKind::Page, 'slug' => 'sample-service', 'title' => 'Sample service', 'template' => PageTemplate::ServiceDetail],
            $payload,
        )->errors();

        $this->assertSame([], $errors);
    }

    /**
     * @return array<string, array{blocks: array<int, string>, optional: array<int, string>}> template => block types and the optional ones, from the spec.
     */
    private function specTemplates(): array
    {
        $spec = (string) file_get_contents(base_path('docs/brand/design-system.md'));
        preg_match('/## Page templates(.*?)\n## /s', $spec, $section);
        preg_match_all('/^\| `([a-z-]+)` \| [^|]+ \| (.+) \|$/m', $section[1] ?? '', $rows, PREG_SET_ORDER);

        $templates = [];

        foreach ($rows as $row) {
            preg_match_all('/`([a-z-]+\/[a-z-]+)`/', $row[2], $blocks);
            preg_match_all('/`([a-z-]+\/[a-z-]+)` \(optional/', $row[2], $optional);
            $templates[$row[1]] = ['blocks' => $blocks[1], 'optional' => $optional[1]];
        }

        return $templates;
    }
}
