<?php

namespace App\Content;

use App\Content\Enums\PageTemplate;

final readonly class FixedPageAssembler
{
    public function __construct(
        private PageTemplateRegistry $templates,
        private BlockRegistry $blocks,
    ) {}

    /**
     * @param  array<string, mixed>  $sections
     * @return array<string, Block>
     */
    public function assemble(PageTemplate $template, array $sections): array
    {
        $definition = $this->templates->definition($template);
        $blocks = [];

        foreach ($definition->sequence as $section) {
            if (! is_array($sections[$section] ?? null)) {
                continue;
            }

            $type = $definition->sectionTypes[$section];
            $blocks[$section] = new Block(
                $type,
                $this->blocks->renderProps($type, $sections[$section]),
            );
        }

        return $blocks;
    }
}
