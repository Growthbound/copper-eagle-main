<?php

namespace App\Content\Enums;

enum PageTemplate: string
{
    case Standard = 'standard';
    case ServicePillar = 'service-pillar';
    case LocationPillar = 'location-pillar';
    case DecisionGuide = 'decision-guide';

    /*
     * Copper Eagle page templates. Section order and block types are listed in
     * docs/brand/design-system.md and fixed in PageTemplateRegistry.
     */
    case Home = 'home';
    case ServicesHub = 'services-hub';
    case ServiceDetail = 'service-detail';
    case Emergency = 'emergency';
    case About = 'about';
    case Team = 'team';
    case Reviews = 'reviews';
    case Contact = 'contact';
}
