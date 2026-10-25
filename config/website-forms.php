<?php

return [
    // Independent of config/referrals.php and its separate HighLevel account.
    'provider' => env('WEBSITE_FORM_PROVIDER', 'native'),
    // Who is emailed about each new lead, comma separated. Used until the
    // owner saves a list under Admin → Notifications; with neither set, the
    // site's contact email gets them.
    'lead_notification_recipients' => env('LEAD_NOTIFICATION_RECIPIENTS'),
    'origin' => env('WEBSITE_HIGHLEVEL_ORIGIN', 'https://api.leadconnectorhq.com'),
    'forms' => [
        'inquiry' => env('WEBSITE_HIGHLEVEL_INQUIRY_FORM_ID'),
        'feedback' => env('WEBSITE_HIGHLEVEL_FEEDBACK_FORM_ID'),
        'comments' => env('WEBSITE_HIGHLEVEL_COMMENTS_FORM_ID'),
    ],
    // Site-specific public inquiry permission; owner settings take precedence.
    'consent' => [
        'mode' => 'choices',
        'required' => false,
        // Single-box wording, used only if the owner switches to the
        // "disclosure" or "checkbox" mode in the admin.
        'text' => 'I agree to receive text messages and calls from Copper Eagle about my request, scheduling, appointment reminders, and service updates, including calls made with automated technology or an artificial or AI-generated voice. Message frequency varies. Message and data rates may apply. Reply STOP to opt out or HELP for help. Consent is not a condition of purchase.',
        // "choices" mode: separate, optional, unticked boxes, each recorded on
        // the lead. Copied word for word from the live contact form at
        // coppereagleplumbing.com/contact-copper-eagle-plumbing, which carriers
        // compare with the registered text campaign. Change it only with the
        // owner's exact registered wording. Keys must be LeadConsent::CHOICE_KINDS.
        'choices_title' => 'Text Message Consent (optional)',
        'choices' => [
            'service' => 'I agree to receive customer care and service text messages from Copper Eagle Plumbing LLC, including appointment confirmations, scheduling updates, technician arrival notices, estimates, and service follow-ups. Message frequency varies. Msg & data rates may apply. Reply HELP for help or STOP to opt out.',
            'marketing' => 'I agree to receive marketing and promotional text messages from Copper Eagle Plumbing LLC, such as special offers, discounts, and seasonal maintenance reminders. Message frequency varies. Msg & data rates may apply. Reply HELP for help or STOP to opt out.',
        ],
        // Rendered as: "<note> See our <privacy link> and <terms link>."
        'choices_note' => 'Both boxes are optional. Consent is not a condition of purchase, and you can submit this form without checking either box.',
        'choices_privacy_link' => 'Privacy Policy at coppereagleplumbing.com/privacy-policy',
        'choices_terms_link' => 'SMS Terms at coppereagleplumbing.com/terms-and-conditions',
    ],
    // Legacy page paths whose published revisions predate `purpose`, mapped
    // to the purpose they should use, e.g. ['contact-us/feedback' => 'feedback'].
    'page_purposes' => [],
];
