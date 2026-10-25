<?php

namespace App\Content\Enums;

enum ApiKeyScope: string
{
    case MediaImport = 'media:import';
    case MediaRead = 'media:read';
    case ContentRead = 'content:read';
    case ContentDraft = 'content:draft';
    case ContentPreview = 'content:preview';
    case ContentPublish = 'content:publish';
    case ContentSchedule = 'content:schedule';
    case ContentRollback = 'content:rollback';
}
