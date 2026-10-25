<?php

/*
|--------------------------------------------------------------------------
| Media library location
|--------------------------------------------------------------------------
|
| Content references images by their stable library id — "/media/43" — never
| by hostname. This setting decides which library those ids resolve against.
|
| Leave it empty and media is served from this application, which is what
| production does. Point it at another install and every page renders that
| library's images without a single document changing: useful for local work
| against the dev site, and for moving the library later.
|
| The catalogue of what each id actually shows lives in
| content/registries/media-index.json.
|
*/

return [
    'base_url' => env('MEDIA_BASE_URL', ''),
];
