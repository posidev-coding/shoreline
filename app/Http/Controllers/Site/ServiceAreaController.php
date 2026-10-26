<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceArea;
use App\Models\Showcase;
use App\Support\Seo;
use Illuminate\View\View;

class ServiceAreaController extends Controller
{
    public function index(): View
    {
        return view('site.areas.index', [
            'areasByCounty' => ServiceArea::query()->published()->ordered()->get()->groupBy(fn (ServiceArea $area) => $area->county->getLabel()),
            'seo' => new Seo(
                title: 'Service Areas',
                description: 'Garage door service and handyman work in Carteret County, from Morehead City and Beaufort to Emerald Isle, plus Swansboro and Havelock.',
                breadcrumbs: [['name' => 'Service Areas', 'url' => route('areas.index')]],
            ),
        ]);
    }

    /**
     * Each town page carries that town's own jobs and reviews, so the pages aren't interchangeable.
     */
    public function show(ServiceArea $serviceArea): View
    {
        abort_unless($serviceArea->is_published, 404);

        return view('site.areas.show', [
            'area' => $serviceArea,
            'showcases' => Showcase::query()->published()->ordered()->whereBelongsTo($serviceArea)
                ->with(['media', 'service', 'serviceArea'])->limit(6)->get(),
            'testimonials' => $serviceArea->testimonials()->published()->ordered()->with('serviceArea')->limit(6)->get(),
            'services' => Service::query()->published()->ordered()->get(),
            'seo' => new Seo(
                title: $serviceArea->seo_title ?: "Garage Doors in {$serviceArea->name}, NC",
                description: $serviceArea->seo_description
                    ?: "Garage door installation, repair, and handyman work in {$serviceArea->name}, NC.",
                breadcrumbs: [
                    ['name' => 'Service Areas', 'url' => route('areas.index')],
                    ['name' => $serviceArea->name, 'url' => route('areas.show', $serviceArea)],
                ],
            ),
        ]);
    }
}
