<?php

namespace App\Content\Definitions;

use App\Content\Enums\PageTemplate;

final readonly class PageTemplateDefinition
{
    /**
     * @param  array<int, string>  $requiredSections
     * @param  array<int, string>  $sequence
     * @param  array<string, string>  $sectionTypes
     */
    public function __construct(
        public PageTemplate $template,
        public string $view,
        public array $requiredSections,
        public array $sequence,
        public array $sectionTypes = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'template' => $this->template->value,
            'view' => $this->view,
            'required_sections' => $this->requiredSections,
            'sequence' => $this->sequence,
            'section_types' => $this->sectionTypes,
        ];
    }
}
