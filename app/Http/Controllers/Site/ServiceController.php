<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceArea;
use App\Models\Showcase;
use App\Support\Seo;
use App\Support\StructuredData;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(): View
    {
        return view('site.services.index', [
            'services' => Service::query()->published()->ordered()->get(),
            'seo' => new Seo(
                title: 'Garage Door & Handyman Services',
                description: 'Garage door installation, repair, springs, openers, and wind-rated doors, plus the odd jobs around the house, across the Crystal Coast.',
                breadcrumbs: [['name' => 'Services', 'url' => route('services.index')]],
            ),
        ]);
    }

    public function show(Service $service): View
    {
        abort_unless($service->is_published, 404);

        $areas = ServiceArea::query()->published()->ordered()->get();
        $breadcrumbs = [
            ['name' => 'Services', 'url' => route('services.index')],
            ['name' => $service->name, 'url' => route('services.show', $service)],
        ];

        return view('site.services.show', [
            'service' => $service,
            'faqs' => $service->faqs()->published()->ordered()->get(),
            'showcases' => Showcase::query()->published()->ordered()->whereBelongsTo($service)
                ->with(['media', 'service', 'serviceArea'])->limit(3)->get(),
            'otherServices' => Service::query()->published()->ordered()->whereKeyNot($service->getKey())->get(),
            'seo' => new Seo(
                title: $service->seo_title ?: $service->name,
                description: $service->seo_description ?: $service->summary,
                breadcrumbs: $breadcrumbs,
                schemas: [StructuredData::service($service, $areas)],
            ),
        ]);
    }
}
