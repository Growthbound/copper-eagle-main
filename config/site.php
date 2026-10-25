<?php

/*
|--------------------------------------------------------------------------
| Business identity
|--------------------------------------------------------------------------
|
| Who this business is, as opposed to what the site says. The admin overrides
| most of these at runtime so the owner can change a phone number without a
| deploy.
|
| Page content does not belong here. Services, service areas, questions,
| testimonials, benefits, process steps, and resources are records in
| content/, because they generate pages and are maintained in the repository.
|
*/

return [
    'name' => 'Copper Eagle',
    'logo_media_id' => null,

    /*
     * The schema.org type for this business, used in structured data. Pick the
     * most specific type that fits: HVACBusiness, Plumber, Electrician,
     * RoofingContractor, HousePainter, MovingCompany. Falls back to
     * LocalBusiness when unset.
     */
    'business_type' => null,

    /*
     * Left empty until the business confirms them. The owner can also set these
     * in the admin under Site details without a deploy.
     */
    'description' => 'Plumbing services in Salt Lake City, Utah.',
    'phone' => '(385) 625-0369',
    'email' => '',
    'address' => '',

    /*
     * Profiles to link from the footer, and to declare as the business's other
     * official presences in structured data.
     */
    'social_links' => [
        ['label' => 'Instagram', 'url' => 'https://www.instagram.com/copper.eagle.plumbing/'],
        ['label' => 'YouTube', 'url' => 'https://www.youtube.com/channel/UCnSBO4aWLcKSncFczU4eRsg'],
        ['label' => 'Facebook', 'url' => 'https://www.facebook.com/coppereagleplumbingut'],
        ['label' => 'TikTok', 'url' => 'https://www.tiktok.com/t/ZP8P2soup/'],
        ['label' => 'Nextdoor', 'url' => 'https://nextdoor.com/page/copper-eagle-plumbing-glenburn-me'],
        ['label' => 'Google reviews', 'url' => 'https://g.page/r/CY-WA3uO3va4kEAE/review'],
    ],

    /*
     * The header menu. A path such as '/services' links to a page; one level
     * of 'children' becomes a dropdown. Add entries as the pages are published.
     */
    'navigation' => [
        ['label' => 'Services', 'href' => '/plumbing-services-salt-lake-city-utah'],
        ['label' => '24/7 Emergency', 'href' => '/24-7-emergency-plumber-salt-lake-utah'],
        ['label' => 'About', 'href' => '/about-jordan-walker-master-plumber-utah', 'children' => [
            ['label' => 'About Us', 'href' => '/about-jordan-walker-master-plumber-utah'],
            ['label' => 'Team', 'href' => '/team'],
            ['label' => 'Reviews', 'href' => '/reviews-copper-eagle-plumbing'],
            ['label' => 'Social Media', 'href' => '/socialmedia'],
        ]],
        ['label' => 'Contact', 'href' => '/contact-copper-eagle-plumbing'],
    ],
];
