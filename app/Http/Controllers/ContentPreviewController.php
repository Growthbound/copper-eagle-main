<?php

namespace App\Http\Controllers;

use App\Content\ContentPreviewService;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ContentPreviewController extends Controller
{
    public function __invoke(
        string $token,
        ContentPreviewService $previews,
    ): Response {
        $preview = $previews->resolve($token);

        if ($preview === null) {
            throw new NotFoundHttpException;
        }

        return response()
            ->view('content-preview', ['preview' => $preview])
            ->withHeaders([
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'Referrer-Policy' => 'no-referrer',
            ]);
    }
}
