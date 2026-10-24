<?php

namespace App\Services\Media;

use RuntimeException;
use Throwable;

class RemoteMediaCleanupFailed extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('Remote media cleanup failed.', previous: $previous);
    }
}
