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
    'name' => 'Copper Eagle Plumbing',
    'logo_media_id' => null,

    /*
     * A photograph of the business for structured data: the crew, a van, the
     * shop, never the logo. A path under public/ with its real size, or null
     * until the business has one. This is the crew photo from the contact page.
     */
    'photo' => [
        'src' => 'images/brand/pages/crew-photo.jpg',
        'width' => 1200,
        'height' => 745,
    ],

    /*
     * The schema.org type for this business, used in structured data. Pick the
     * most specific type that fits: HVACBusiness, Plumber, Electrician,
     * RoofingContractor, HousePainter, MovingCompany. Falls back to
     * LocalBusiness when unset.
     */
    'business_type' => 'Plumber',

    /*
     * Left empty until the business confirms them. The owner can also set these
     * in the admin under Site details without a deploy.
     */
    'description' => 'Copper Eagle Plumbing is a plumbing company in Salt Lake City, Utah, for drain cleaning, water heaters, water filtration, gas lines, steam showers and remodel plumbing.',
    'phone' => '(385) 625-0369',
    // Shown on the live site's contact page, and where leads go by default.
    'email' => 'diamond9utah@gmail.com',
    'address' => '',

    /*
     * Opening hours for structured data. Each entry lists days and a time
     * range; a 24/7 business lists every day from 00:00 to 23:59. Leave empty
     * to declare none. Copper Eagle's own pages say it answers 24/7, 365 days
     * a year.
     */
    'opening_hours' => [
        [
            'days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            'opens' => '00:00',
            'closes' => '23:59',
        ],
    ],

    /*
     * People the business names on its own pages, declared in structured data
     * so search engines and AI answers can connect them to the business. Use
     * only names and titles the business publishes.
     */
    'people' => [
        [
            'name' => 'Jordan Walker',
            'job_title' => 'Owner and Master Plumber',
            'url' => '/about-jordan-walker-master-plumber-utah',
        ],
    ],

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
