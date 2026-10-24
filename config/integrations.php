<?php

/*
|--------------------------------------------------------------------------
| Third-party tracking and widgets
|--------------------------------------------------------------------------
|
| One place for every ID a marketing site needs. Values are entered in
| Admin → Tracking and stored in the settings table, which lives in the SQLite
| database on the persistent data volume — so they survive image rebuilds and
| are never committed to the repository.
|
| A snippet renders only when its value is set, so a new site starts with no
| third-party JavaScript at all.
|
| 'pattern' is validated on save. Catching a malformed ID here is much cheaper
| than discovering weeks later that a site recorded no analytics.
|
*/

return [
    'fields' => [
        'ga4_measurement_id' => [
            'label' => 'Google Analytics 4',
            'help' => 'Measurement ID, formatted like G-XXXXXXXXXX.',
            'placeholder' => 'G-XXXXXXXXXX',
            'pattern' => '/^G-[A-Z0-9]{6,12}$/i',
            'pattern_hint' => 'A GA4 measurement ID looks like G-XXXXXXXXXX.',
        ],
        'gtm_container_id' => [
            'label' => 'Google Tag Manager',
            'help' => 'Container ID. Use this instead of the individual IDs below if the client runs everything through GTM.',
            'placeholder' => 'GTM-XXXXXXX',
            'pattern' => '/^GTM-[A-Z0-9]{5,10}$/i',
            'pattern_hint' => 'A GTM container ID looks like GTM-XXXXXXX.',
        ],
        'clarity_project_id' => [
            'label' => 'Microsoft Clarity',
            'help' => 'Project ID from the Clarity dashboard URL.',
            'placeholder' => 'abcdefghij',
            'pattern' => '/^[a-z0-9]{6,20}$/i',
            'pattern_hint' => 'A Clarity project ID is letters and numbers only.',
        ],
        'meta_pixel_id' => [
            'label' => 'Meta Pixel',
            'help' => 'Numeric pixel ID from Events Manager.',
            'placeholder' => '123456789012345',
            'pattern' => '/^[0-9]{8,20}$/',
            'pattern_hint' => 'A Meta pixel ID is numbers only.',
        ],
        'posthog_key' => [
            'label' => 'PostHog project key',
            'help' => 'Leave blank unless the client uses PostHog.',
            'placeholder' => 'phc_xxxxxxxxxxxxxxxx',
            'pattern' => '/^phc_[A-Za-z0-9]{10,}$/',
            'pattern_hint' => 'A PostHog project key starts with phc_.',
        ],
        'posthog_host' => [
            'label' => 'PostHog host',
            'help' => 'Only needed for self-hosted PostHog. Defaults to the US cloud.',
            'placeholder' => 'https://us.i.posthog.com',
            'pattern' => '/^https:\/\/[^\s]+$/',
            'pattern_hint' => 'The PostHog host must be a full https:// URL.',
        ],
    ],

    /*
     * Escape hatches for anything without a dedicated field above: chat
     * widgets, call tracking, heat maps, verification tags.
     *
     * These are injected verbatim. Only the signed-in owner can set them.
     */
    'snippets' => [
        'head_snippet' => [
            'label' => 'Custom <head> code',
            'help' => 'Verification tags and anything that must load early. Paste the full snippet including its <script> tags.',
        ],
        'body_snippet' => [
            'label' => 'Custom code before </body>',
            'help' => 'Chat widgets and call tracking belong here so they do not slow the first paint.',
        ],
    ],
];
