<?php

namespace App\Http\Controllers;

use App\Content\Area;
use App\Content\AreaRepository;
use App\Content\ManagedContentRepository;
use App\Content\Service;
use App\Content\ServiceRepository;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AreaController extends Controller
{
    public function __construct(
        private readonly AreaRepository $areas,
        private readonly ServiceRepository $services,
        private readonly ManagedContentRepository $pages,
    ) {}

    public function index(): View
    {
        if ($override = $this->override('areas')) {
            return $override;
        }

        return view('content.areas.index', [
            'areas' => $this->areas->all(),
        ]);
    }

    public function show(string $area): View
    {
        if ($override = $this->override('areas/'.$area)) {
            return $override;
        }

        $record = $this->areas->find($area);

        if (! $record instanceof Area) {
            throw new NotFoundHttpException;
        }

        return view('content.areas.show', [
            'area' => $record,
            'services' => $this->services->all()->filter(
                fn (Service $service): bool => $service->servesArea($record)
            )->values(),
        ]);
    }

    private function override(string $slug): ?View
    {
        $page = $this->pages->findPage($slug);

        return $page === null ? null : view('page', ['page' => $page]);
    }
}
