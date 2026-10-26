<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Service;
use App\Support\Seo;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function __invoke(): View
    {
        return view('site.faq', [
            'generalFaqs' => Faq::query()->published()->ordered()->whereNull('service_id')->get(),
            'services' => Service::query()->published()->ordered()
                ->whereHas('faqs', fn ($query) => $query->published())
                ->with(['faqs' => fn ($query) => $query->published()->ordered()])
                ->get(),
            'seo' => new Seo(
                title: 'Frequently Asked Questions',
                description: 'Answers about garage door repair, springs, openers, storm prep, scheduling, and the odd jobs we take on.',
                breadcrumbs: [['name' => 'FAQ', 'url' => route('faq')]],
            ),
        ]);
    }
}
