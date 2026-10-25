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
    'social_links' => [],

    /*
     * The header menu. A path such as '/services' links to a page; one level
     * of 'children' becomes a dropdown. Add entries as the pages are published.
     */
    'navigation' => [],
];
