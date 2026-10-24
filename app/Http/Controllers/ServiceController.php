<?php

namespace App\Http\Controllers;

use App\Content\Area;
use App\Content\AreaRepository;
use App\Content\ManagedContentRepository;
use App\Content\Service;
use App\Content\ServiceRepository;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Pages generated from service records.
 *
 * Every route here checks content/pages first: dropping a page file at the
 * matching slug replaces the generated page entirely. Records give a correct
 * page for free; a page file is how a page that deserves bespoke treatment
 * gets it, without a special case in the code.
 */
class ServiceController extends Controller
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly AreaRepository $areas,
        private readonly ManagedContentRepository $pages,
    ) {}

    public function index(): View
    {
        if ($override = $this->override('services')) {
            return $override;
        }

        return view('content.services.index', [
            'services' => $this->services->all(),
        ]);
    }

    public function show(string $service): View
    {
        // Checked before the record is resolved: a page file stands on its own,
        // including for a slug that has no record behind it.
        if ($override = $this->override('services/'.$service)) {
            return $override;
        }

        $record = $this->service($service);

        return view('content.services.show', [
            'service' => $record,
            'areas' => $this->areas->all()->filter(
                fn (Area $area): bool => $record->servesArea($area)
            )->values(),
        ]);
    }

    public function inArea(string $service, string $area): View
    {
        if ($override = $this->override('services/'.$service.'/'.$area)) {
            return $override;
        }

        throw new NotFoundHttpException;
    }

    private function service(string $slug): Service
    {
        $record = $this->services->find($slug);

        if (! $record instanceof Service) {
            throw new NotFoundHttpException;
        }

        return $record;
    }

    private function override(string $slug): ?View
    {
        $page = $this->pages->findPage($slug);

        return $page === null ? null : view('page', ['page' => $page]);
    }
}
