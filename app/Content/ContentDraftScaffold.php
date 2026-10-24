<?php

namespace App\Content;

use App\Content\Enums\PageTemplate;
use App\Support\Concerns\FictionalTemplateContent;

final class ContentDraftScaffold
{
    use FictionalTemplateContent;

    /**
     * @return array<string, mixed>
     */
    public function for(PageTemplate $template, string $title): array
    {
        if ($template === PageTemplate::Standard) {
            return [
                'title' => $title,
                'description' => null,
                'blocks' => [],
            ];
        }

        return [
            ...$this->fixedPayload($template, $title),
            'description' => 'Replace this draft description before publication.',
            'noindex' => true,
            '_scaffold' => true,
        ];
    }
}
