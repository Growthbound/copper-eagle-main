<?php

namespace App\Content\Exceptions;

use App\Content\Validation\ContentValidationResult;
use RuntimeException;

final class ContentValidationFailed extends RuntimeException
{
    public function __construct(public readonly ContentValidationResult $result)
    {
        parent::__construct('Managed content failed validation.');
    }
}
