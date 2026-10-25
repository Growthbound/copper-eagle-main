<?php

namespace App\Support;

use App\Content\Block;
use App\Content\Enums\PageTemplate;
use App\Content\FixedPageAssembler;
use App\Support\Concerns\FictionalTemplateContent;

final class TemplatePreviewContent
{
    use FictionalTemplateContent;

    /**
     * @return array{title: string, description: string, sections: array<string, Block>}
     */
    public function page(PageTemplate $template, string $title): array
    {
        $payload = $this->fixedPayload($template, $title);

        return [
            'title' => $payload['title'],
            'description' => $payload['description'],
            'sections' => app(FixedPageAssembler::class)->assemble(
                $template,
                $payload['sections'],
            ),
        ];
    }
}
