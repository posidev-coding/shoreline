<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceArea;
use App\Models\Showcase;
use App\Models\Testimonial;
use App\Settings\SiteSettings;
use App\Support\Seo;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(SiteSettings $settings): View
    {
        return view('site.home', [
            'services' => Service::query()->published()->ordered()->get(),
            'showcases' => Showcase::query()->published()->ordered()->with(['media', 'service', 'serviceArea'])->limit(3)->get(),
            'testimonials' => Testimonial::query()->published()->ordered()->with('serviceArea')->limit(3)->get(),
            'areas' => ServiceArea::query()->published()->ordered()->get(),
            'seo' => new Seo(
                title: "{$settings->business_name} | Garage Doors in Morehead City, NC",
                description: $settings->hero_subhead,
                isHome: true,
            ),
        ]);
    }
}
