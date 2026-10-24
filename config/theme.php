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
            'default' => '#2b1d16',
            'contrast_against' => 'white',
        ],
        'orange' => [
            'label' => 'Accent',
            'help' => 'Primary buttons, icon tiles, and highlights.',
            'default' => '#d4874a',
            'contrast_against' => 'ink',
        ],
        'orange-ink' => [
            'label' => 'Accent text on light',
            'help' => 'Accent-colored words sitting on white sections.',
            'default' => '#8a4a1f',
            'contrast_against' => 'white',
        ],
        'orange-light' => [
            'label' => 'Accent text on dark',
            'help' => 'Accent-colored words sitting on primary panels.',
            'default' => '#f2c59a',
            'contrast_against' => 'teal',
        ],
        'mist' => [
            'label' => 'Soft background',
            'help' => 'Tinted section backgrounds behind cards.',
            'default' => '#f8f5f1',
            'contrast_against' => 'ink',
        ],
        'ink' => [
            'label' => 'Body text',
            'help' => 'Headings and body copy on light backgrounds.',
            'default' => '#0d0d0d',
            'contrast_against' => 'white',
        ],
        'muted' => [
            'label' => 'Secondary text',
            'help' => 'Supporting paragraphs and captions.',
            'default' => '#6f6f6f',
            'contrast_against' => 'white',
        ],
    ],

    /*
     * Not editable, but needed as the light end of several contrast pairs.
     */
    'fixed' => [
        'white' => '#ffffff',
    ],
];
