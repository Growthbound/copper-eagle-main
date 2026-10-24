<?php

namespace App\Http\Controllers;

use App\Content\ManagedContentRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PageController extends Controller
{
    public function __construct(private readonly ManagedContentRepository $pages) {}

    public function __invoke(Request $request): View
    {
        $page = $this->pages->findPage($request->path());

        if ($page === null) {
            throw new NotFoundHttpException;
        }

        return view('page', ['page' => $page]);
    }
}
