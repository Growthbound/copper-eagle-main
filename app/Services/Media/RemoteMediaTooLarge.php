<?php

namespace App\Services\Media;

class RemoteMediaTooLarge extends RemoteMediaFetchFailed
{
    public function __construct()
    {
        parent::__construct(
            errorCode: 'too_large',
            safeMessage: 'The remote file exceeds the allowed size.',
        );
    }
}
