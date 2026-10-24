<?php

namespace App\Content\Enums;

enum ContentSource: string
{
    case Admin = 'admin';
    case Api = 'api';
    case Import = 'import';
}
