<?php

namespace App\Content;

use RuntimeException;

/**
 * Raised when a page file cannot be turned into a page.
 *
 * The message is written to be actionable on its own, because it is what an
 * author sees from `php artisan content:check` without any other context.
 */
class InvalidPage extends RuntimeException
{
    public function __construct(
        public readonly string $slug,
        string $problem,
    ) {
        parent::__construct($problem);
    }

    public function describe(): string
    {
        return 'content/pages'.($this->slug === '/' ? '/home' : '/'.$this->slug).'.json: '.$this->getMessage();
    }
}
