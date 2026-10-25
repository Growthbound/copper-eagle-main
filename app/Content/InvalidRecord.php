<?php

namespace App\Content;

use RuntimeException;

class InvalidRecord extends RuntimeException
{
    public function __construct(
        public readonly string $directory,
        public readonly string $slug,
        string $problem,
    ) {
        parent::__construct(sprintf(
            '%s/%s.json: the file %s',
            str_replace(base_path().'/', '', $directory),
            $slug,
            $problem,
        ));
    }
}
