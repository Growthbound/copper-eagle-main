<?php

namespace App\Services\Media;

use RuntimeException;

class UnsafeRemoteUrl extends RuntimeException
{
    public readonly string $errorCode;

    public readonly string $safeMessage;

    public function __construct(string $message = 'The remote URL is not a public HTTP or HTTPS destination.')
    {
        parent::__construct($message);

        $this->errorCode = 'unsafe_url';
        $this->safeMessage = 'The source URL is not an allowed public destination.';
    }
}
