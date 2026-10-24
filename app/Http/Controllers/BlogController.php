<?php

namespace App\Http\Controllers;

use App\Content\Enums\ContentKind;
use App\Content\ManagedContentRepository;
use Illuminate\Contracts\View\View;

class BlogController extends Controller
{
    public function __invoke(ManagedContentRepository $content): View
    {
        return view('blog.index', [
            'articles' => $content->publishedByKind(ContentKind::Article)
                ->reject(fn ($article): bool => $article->noindex),
        ]);
    }
}
