<?php

namespace App\Content\Enums;

enum ContentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Scheduled = 'scheduled';
    case Archived = 'archived';
}
