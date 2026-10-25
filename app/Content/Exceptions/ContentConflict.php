<?php

namespace App\Content\Exceptions;

use RuntimeException;

final class ContentConflict extends RuntimeException
{
    public function __construct(
        public readonly int $expectedLockVersion,
        public readonly int $currentLockVersion,
    ) {
        parent::__construct(
            "Content changed after this edit began (expected version {$expectedLockVersion}, current version {$currentLockVersion}).",
        );
    }
}
