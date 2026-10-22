<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceArea;
use App\Models\Showcase;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShowcaseController extends Controller
{
    /**
     * Filtering is a plain GET form, so it works without JavaScript and each filtered URL caches on its own.
     */
    public function index(Request $request): View
    {
        $services = Service::query()->published()->ordered()->get();
        $areas = ServiceArea::query()->published()->ordered()->get();
        $service = $services->firstWhere('slug', $request->query('service'));
        $area = $areas->firstWhere('slug', $request->query('town'));

        $showcases = Showcase::query()->published()->ordered()
            ->with(['media', 'service', 'serviceArea'])
            ->when($service, fn ($query, Service $service) => $query->whereBelongsTo($service))
            ->when($area, fn ($query, ServiceArea $area) => $query->whereBelongsTo($area))
            ->paginate(12)
            ->withQueryString();

        return view('site.work.index', [
            'showcases' => $showcases,
            'services' => $services,
            'areas' => $areas,
            'selectedService' => $service,
            'selectedArea' => $area,
            'seo' => new Seo(
                title: 'Our Work: Before & After',
                description: 'Garage doors we’ve installed and repaired across Carteret County, Swansboro, and Havelock. Drag to compare before and after.',
                canonical: route('work.index'),
                breadcrumbs: [['name' => 'Our Work', 'url' => route('work.index')]],
            ),
        ]);
    }

    public function show(Showcase $showcase): View
    {
        abort_unless($showcase->isPublic(), 404);

        $showcase->load(['media', 'service', 'serviceArea']);
        $after = $showcase->getFirstMedia('after');

        return view('site.work.show', [
            'showcase' => $showcase,
            'related' => Showcase::query()->published()->ordered()->whereKeyNot($showcase->getKey())
                ->with(['media', 'service', 'serviceArea'])->limit(3)->get(),
            'seo' => new Seo(
                title: $showcase->title,
                description: str($showcase->story)->stripTags()->squish()->limit(150)->toString()
                    ?: "A {$showcase->service?->name} job in {$showcase->serviceArea?->name}, NC.",
                image: $after?->hasGeneratedConversion('w1280') ? $after->getUrl('w1280') : null,
                type: 'article',
                breadcrumbs: [
                    ['name' => 'Our Work', 'url' => route('work.index')],
                    ['name' => $showcase->title, 'url' => route('work.show', $showcase)],
                ],
            ),
        ]);
    }
}
