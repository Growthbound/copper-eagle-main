<?php

namespace App\ValueObjects;

use App\Models\Media;

class MediaImportResult
{
    public function __construct(
        public readonly string $status,
        public readonly string $sourceUrl,
        public readonly ?Media $media = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $message = null,
    ) {}
}
