<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use App\Support\Seo;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function __invoke(): View
    {
        return view('site.reviews', [
            'testimonials' => Testimonial::query()->published()->ordered()->with(['serviceArea', 'service'])->get(),
            'seo' => new Seo(
                title: 'Reviews',
                description: 'What customers on the Crystal Coast say about their garage door and handyman work.',
                breadcrumbs: [['name' => 'Reviews', 'url' => route('reviews')]],
            ),
        ]);
    }
}
