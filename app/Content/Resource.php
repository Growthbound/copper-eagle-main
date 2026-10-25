<?php

namespace App\Content;

class Resource extends Record
{
    public function url(): string
    {
        return route('home').'#resources';
    }
}
