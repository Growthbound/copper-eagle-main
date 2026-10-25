<?php

namespace App\Content;

class ProcessStep extends Record
{
    /** The step number as written, so leading zeros survive. */
    public function number(): string
    {
        return (string) $this->get('number', '');
    }

    public function url(): string
    {
        return route('home').'#process';
    }
}
