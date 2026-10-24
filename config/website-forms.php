<?php

return [
    // Independent of config/referrals.php and its separate HighLevel account.
    'provider' => env('WEBSITE_FORM_PROVIDER', 'native'),
    'origin' => env('WEBSITE_HIGHLEVEL_ORIGIN', 'https://api.leadconnectorhq.com'),
    'forms' => [
        'inquiry' => env('WEBSITE_HIGHLEVEL_INQUIRY_FORM_ID'),
        'feedback' => env('WEBSITE_HIGHLEVEL_FEEDBACK_FORM_ID'),
        'comments' => env('WEBSITE_HIGHLEVEL_COMMENTS_FORM_ID'),
    ],
    // Site-specific public inquiry permission; owner settings take precedence.
    'consent' => [
        'mode' => 'checkbox',
        'required' => false,
        'text' => 'I agree to receive text messages and calls from Copper Eagle about my request, scheduling, appointment reminders, and service updates, including calls made with automated technology or an artificial or AI-generated voice. Message frequency varies. Message and data rates may apply. Reply STOP to opt out or HELP for help. Consent is not a condition of purchase.',
    ],
    // Legacy page paths whose published revisions predate `purpose`, mapped
    // to the purpose they should use, e.g. ['contact-us/feedback' => 'feedback'].
    'page_purposes' => [],
];
