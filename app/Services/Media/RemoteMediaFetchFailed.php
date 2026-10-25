<?php

namespace App\Services\Media;

use RuntimeException;
use Throwable;

class RemoteMediaFetchFailed extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode = 'fetch_failed',
        public readonly string $safeMessage = 'The remote file could not be downloaded.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($safeMessage, previous: $previous);
    }
}
