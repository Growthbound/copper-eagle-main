<?php

namespace App\Content;

use App\Models\ContentPreviewToken;

final readonly class IssuedPreview
{
    public function __construct(
        public ContentPreviewToken $preview,
        public string $token,
    ) {}

    public function url(): string
    {
        return route('content.preview', ['token' => $this->token]);
    }
}
