<?php

/*
|--------------------------------------------------------------------------
| Editable brand colors
|--------------------------------------------------------------------------
|
| Each entry maps to a CSS custom property of the same name in
| resources/css/app.css. The defaults below must stay in sync with the
| @theme block there, which is what the site falls back to when a color has
| never been saved in the admin theme editor.
|
| 'contrast_against' names the token this color must remain readable on. The
| admin editor scores those pairs so the owner sees a warning before saving a
| combination that fails WCAG AA.
|
*/

return [
    'colors' => [
        'teal' => [
            'label' => 'Primary',
            'help' => 'Dark panels, footers, and the secondary button.',
            'default' => '#284758',
            'contrast_against' => 'white',
        ],
        'orange' => [
            'label' => 'Accent',
            'help' => 'Primary buttons, hairlines, and highlights. Button text is white.',
            'default' => '#a56542',
            'contrast_against' => 'white',
        ],
        'orange-ink' => [
            'label' => 'Accent text on light',
            'help' => 'Accent-colored words sitting on white sections.',
            'default' => '#9a5a38',
            'contrast_against' => 'white',
        ],
        'orange-light' => [
            'label' => 'Accent text on dark',
            'help' => 'Accent-colored words sitting on primary panels.',
            'default' => '#e8b591',
            'contrast_against' => 'teal',
        ],
        'mist' => [
            'label' => 'Soft background',
            'help' => 'Tinted section backgrounds behind cards.',
            'default' => '#edf2f9',
            'contrast_against' => 'ink',
        ],
        'ink' => [
            'label' => 'Body text',
            'help' => 'Headings and body copy on light backgrounds.',
            'default' => '#42474a',
            'contrast_against' => 'white',
        ],
        'muted' => [
            'label' => 'Secondary text',
            'help' => 'Supporting paragraphs and captions.',
            'default' => '#5c6367',
            'contrast_against' => 'white',
        ],
    ],

    /*
     * The brand's own logo files, used whenever the owner has not uploaded a
     * logo in the admin. Horizontal for the header, stacked for the footer
     * notch and for structured data. See docs/brand/design-system.md.
     */
    'logos' => [
        'horizontal' => ['src' => 'images/brand/logo-horizontal.png', 'width' => 1200, 'height' => 245],
        'stacked' => ['src' => 'images/brand/logo-stacked.png', 'width' => 936, 'height' => 1012],
    ],

    /*
     * Not editable, but needed as the light end of several contrast pairs.
     */
    'fixed' => [
        'white' => '#ffffff',
    ],
];
