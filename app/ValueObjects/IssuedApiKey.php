<?php

namespace App\ValueObjects;

use App\Models\ApiKey;

final readonly class IssuedApiKey
{
    public function __construct(
        public ApiKey $key,
        public string $plaintext,
    ) {}
}
