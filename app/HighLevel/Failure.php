<?php

namespace App\HighLevel;

use RuntimeException;

class Failure extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false, public readonly bool $ambiguous = false)
    {
        parent::__construct($message);
    }
}
