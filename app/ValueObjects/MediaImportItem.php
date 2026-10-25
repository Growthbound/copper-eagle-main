<?php

namespace App\ValueObjects;

class MediaImportItem
{
    public function __construct(
        public readonly string $sourceUrl,
        public readonly ?string $title = null,
        public readonly ?string $alt = null,
        public readonly ?string $handle = null,
    ) {}
}
