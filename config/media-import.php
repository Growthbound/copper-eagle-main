<?php

return [
    'api_url' => env('MEDIA_IMPORT_API_URL'),
    'api_user' => env('MEDIA_IMPORT_API_USER'),
    'api_key' => env('MEDIA_IMPORT_API_KEY'),
    'max_items' => 25,
    'max_bytes' => 25 * 1024 * 1024,
    'connect_timeout' => 5,
    'timeout' => 30,
    'max_redirects' => 3,
];
