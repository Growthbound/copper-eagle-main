<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Services\Media\RemoteMediaImporter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicMediaController extends Controller
{
    public function __invoke(Media $media, RemoteMediaImporter $importer): BinaryFileResponse
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($media->path) && filled($media->source_url)) {
            $importer->restore($media);
        }

        abort_unless($disk->exists($media->path), 404);

        return response()->file($disk->path($media->path), [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
