<?php

namespace App\Content\Enums;

enum PageTemplate: string
{
    case Standard = 'standard';
    case ServicePillar = 'service-pillar';
    case LocationPillar = 'location-pillar';
    case DecisionGuide = 'decision-guide';
}
