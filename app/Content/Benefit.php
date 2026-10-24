<?php

namespace App\Content;

class Benefit extends Record
{
    public function url(): string
    {
        return route('home').'#benefits';
    }
}
