<?php

namespace App\Http\Controllers\Site;

use App\Enums\PageKey;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\HurricaneReadiness;
use App\Support\Seo;
use Illuminate\View\View;

class HurricaneReadyController extends Controller
{
    public function __invoke(): View
    {
        $page = Page::for(PageKey::HurricaneReady);

        return view('site.hurricane-ready', [
            'page' => $page,
            'questions' => HurricaneReadiness::QUESTIONS,
            'seo' => new Seo(
                title: $page->seo_title ?: $page->title,
                description: $page->seo_description ?: (string) $page->intro,
                breadcrumbs: [['name' => $page->title, 'url' => route('pages.hurricane-ready')]],
            ),
        ]);
    }
}
