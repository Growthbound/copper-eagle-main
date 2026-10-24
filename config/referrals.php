<?php

return [
    // The homeowner form referral links open. Required before cards are issued.
    'form_url' => env('REFERRAL_FORM_URL'),
    // Choices on the affiliate form. They must match the HighLevel multi-select field exactly.
    'service_areas' => ['Other'],
    'highlevel' => [
        'token' => env('GHL_PRIVATE_INTEGRATION_TOKEN'),
        'location_id' => env('GHL_LOCATION_ID'),
        // Optional: use the newer paginated user search when the agency ID is available.
        'company_id' => env('GHL_COMPANY_ID'),
        'excluded_user_ids' => array_values(array_filter(array_map('trim', explode(',', env('GHL_EXCLUDED_USER_IDS', ''))))),
        'fields' => [
            'code' => env('GHL_REFERRAL_CODE_FIELD_ID'),
            'source' => env('GHL_REFERRAL_SOURCE_FIELD_ID'),
            'business' => env('GHL_REFERRAL_BUSINESS_FIELD_ID'),
            'main_contact' => env('GHL_REFERRAL_MAIN_CONTACT_FIELD_ID'),
            'url' => env('GHL_REFERRAL_URL_FIELD_ID'),
            'service_areas' => env('GHL_REFERRAL_SERVICE_AREAS_FIELD_ID'),
        ],
    ],
];
