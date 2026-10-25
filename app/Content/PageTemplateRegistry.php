<?php

namespace App\Content;

use App\Content\Definitions\PageTemplateDefinition;
use App\Content\Enums\PageTemplate;
use App\Content\Validation\ContentValidationResult;

final class PageTemplateRegistry
{
    public function __construct(
        private readonly BlockRegistry $blocks,
        private readonly PageAnchorValidator $anchors,
    ) {}

    public function definition(PageTemplate $template): PageTemplateDefinition
    {
        return match ($template) {
            PageTemplate::Standard => new PageTemplateDefinition(
                template: $template,
                view: 'page-templates.standard',
                requiredSections: [],
                sequence: [],
                sectionTypes: [],
            ),
            PageTemplate::ServicePillar => $this->fixed(
                $template,
                [
                    'hero/service' => 'hero/service',
                    'trust-proof' => 'stats/proof',
                    'problem-stakes' => 'content/rich-text',
                    'story-solution' => 'content/rich-text',
                    'transformation' => 'before-after/slider',
                    'offer-inclusions-exclusions' => 'content/inclusions-exclusions',
                    'primary-response' => 'cta/inline-band',
                    'content/direct-answer' => 'content/direct-answer',
                    'decision-comparison' => 'content/comparison-table',
                    'process-details' => 'content/rich-text',
                    'pricing/factors' => 'pricing/factors',
                    'limitations' => 'content/rich-text',
                    'evidence-media' => 'evidence/case-study',
                    'applicable-locations' => 'service-areas/grid',
                    'faq' => 'faq/accordion',
                    'content/sources-reviewer' => 'content/sources-reviewer',
                    'related-content-final-response' => 'content/related-content',
                ],
            ),
            PageTemplate::LocationPillar => $this->fixed(
                $template,
                [
                    'hero/location' => 'hero/location',
                    'local-trust' => 'stats/proof',
                    'local-problem-stakes' => 'content/rich-text',
                    'local-story-solution' => 'content/rich-text',
                    'transformation' => 'before-after/slider',
                    'local-offer' => 'content/inclusions-exclusions',
                    'primary-response' => 'cta/inline-band',
                    'content/direct-answer' => 'content/direct-answer',
                    'available-services' => 'content/related-content',
                    'verified-local-context' => 'content/rich-text',
                    'process-pricing-factors' => 'pricing/factors',
                    'local-evidence' => 'evidence/local-project',
                    'faq' => 'faq/accordion',
                    'nearby-served-areas' => 'service-areas/grid',
                    'content/sources-reviewer' => 'content/sources-reviewer',
                    'related-content-final-response' => 'content/related-content',
                ],
            ),
            PageTemplate::DecisionGuide => $this->fixed(
                $template,
                [
                    'hero/article' => 'hero/article',
                    'content/direct-answer' => 'content/direct-answer',
                    'content/table-of-contents' => 'content/table-of-contents',
                    'context' => 'content/rich-text',
                    'evidence' => 'evidence/case-study',
                    'decision-comparison' => 'content/comparison-table',
                    'actionable-steps' => 'content/rich-text',
                    'limitations' => 'content/rich-text',
                    'faq' => 'faq/accordion',
                    'content/sources-reviewer' => 'content/sources-reviewer',
                    'related-content-next-step' => 'content/related-content',
                ],
            ),
            PageTemplate::Home => $this->fixed($template, [
                'hero' => 'hero/photo',
                'introduction' => 'content/split',
                'testimonials' => 'testimonials/quotes',
                'assurances' => 'content/feature-cards',
                'service-areas' => 'service-areas/list',
                'help-prompt' => 'cta/inline-band',
            ]),
            PageTemplate::ServicesHub => $this->fixed($template, [
                'hero' => 'hero/interior',
                'services' => 'service-cards/grid',
                'not-sure' => 'cta/inline-band',
                'reviews-teaser' => 'content/split',
            ]),
            PageTemplate::ServiceDetail => $this->fixed($template, [
                'hero' => 'hero/interior',
                'introduction' => 'content/split',
                'offerings' => 'content/feature-cards',
                'details' => 'content/split',
                'guarantee' => 'cta/panel',
                'faq' => 'faq/accordion',
                'final-response' => 'cta/inline-band',
            ]),
            PageTemplate::Emergency => $this->fixed($template, [
                'hero' => 'hero/interior',
                'reassurance' => 'content/split',
                'what-to-expect' => 'pricing/factors',
                'final-response' => 'cta/inline-band',
            ]),
            PageTemplate::About => $this->fixed($template, [
                'hero' => 'hero/interior',
                'story' => 'content/split',
                'values' => 'content/feature-cards',
                'final-response' => 'cta/inline-band',
            ]),
            PageTemplate::Team => $this->fixed($template, [
                'hero' => 'hero/interior',
                'team' => 'team/grid',
                'final-response' => 'cta/inline-band',
            ]),
            PageTemplate::Reviews => $this->fixed($template, [
                'hero' => 'hero/interior',
                'testimonials' => 'testimonials/quotes',
                'leave-a-review' => 'cta/panel',
                'final-response' => 'cta/inline-band',
            ]),
            PageTemplate::Contact => $this->fixed($template, [
                'hero' => 'hero/interior',
                'message' => 'cta/quote-form-split',
                'service-areas' => 'service-areas/list',
                'emergency' => 'cta/panel',
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function validate(
        PageTemplate $template,
        array $payload,
        array $unavailablePublicPaths = [],
    ): ContentValidationResult {
        $definition = $this->definition($template);
        $sections = is_array($payload['sections'] ?? null)
            ? $payload['sections']
            : $payload;
        $errors = [];
        $warnings = [];

        foreach ($definition->requiredSections as $section) {
            if (! array_key_exists($section, $sections)) {
                $errors["/sections/{$section}"] = ["The {$section} section is required."];
            }
        }

        foreach ($sections as $section => $props) {
            if (! is_string($section) || ! isset($definition->sectionTypes[$section])) {
                $errors["/sections/{$section}"][] = 'This section is not allowed by the selected page template.';

                continue;
            }

            if (! is_array($props)) {
                $errors["/sections/{$section}"][] = 'Section props must be an object.';

                continue;
            }

            if (array_key_exists('type', $props)) {
                $errors["/sections/{$section}/type"][] = 'Fixed section content cannot choose its rendered block type.';

                continue;
            }

            $result = $this->blocks->validate(
                $definition->sectionTypes[$section],
                $props,
                $unavailablePublicPaths,
            );
            foreach ($result->errors() as $pointer => $messages) {
                $errors["/sections/{$section}{$pointer}"] = $messages;
            }
            foreach ($result->warnings() as $pointer => $messages) {
                $warnings["/sections/{$section}{$pointer}"] = $messages;
            }
        }

        $anchorSections = [];

        foreach ($definition->sequence as $section) {
            if (is_array($sections[$section] ?? null)) {
                $anchorSections[] = [
                    'type' => $definition->sectionTypes[$section],
                    'props' => $sections[$section],
                    'pointer' => "/sections/{$section}",
                ];
            }
        }

        $anchorValidation = $this->anchors->validate($anchorSections);
        $errors = array_merge_recursive($errors, $anchorValidation->errors());
        $warnings = array_merge_recursive($warnings, $anchorValidation->warnings());

        return new ContentValidationResult($errors, $warnings);
    }

    /**
     * @return array<string, PageTemplateDefinition>
     */
    public function all(): array
    {
        $definitions = [];

        foreach (PageTemplate::cases() as $template) {
            $definitions[$template->value] = $this->definition($template);
        }

        return $definitions;
    }

    /**
     * @param  array<string, string>  $sectionTypes
     */
    private function fixed(PageTemplate $template, array $sectionTypes): PageTemplateDefinition
    {
        return new PageTemplateDefinition(
            template: $template,
            view: 'page-templates.'.$template->value,
            requiredSections: array_keys($sectionTypes),
            sequence: array_keys($sectionTypes),
            sectionTypes: $sectionTypes,
        );
    }
}
