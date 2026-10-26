<?php

namespace App\Http\Controllers\Site;

use App\Enums\PageKey;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\ServiceArea;
use App\Support\Seo;
use Illuminate\View\View;

class PageController extends Controller
{
    /**
     * About, Privacy, and Terms: owner-edited copy at a fixed route.
     */
    public function __invoke(string $key): View
    {
        $page = Page::for(PageKey::from($key));

        return view('site.page', [
            'page' => $page,
            // The legal drafts carry a literal {{ last_updated }} placeholder; it isn't Blade.
            'body' => str_replace('{{ last_updated }}', $page->updated_at?->format('F j, Y') ?? '', $page->body),
            'seo' => new Seo(
                title: $page->seo_title ?: $page->title,
                description: $page->seo_description ?: (string) $page->intro,
                breadcrumbs: [['name' => $page->title, 'url' => route($page->key->routeName())]],
            ),
        ]);
    }

    public function contact(): View
    {
        return view('site.contact', [
            'areas' => ServiceArea::query()->published()->ordered()->get(),
            'seo' => new Seo(
                title: 'Contact',
                description: 'Call, text, or email Shoreline Doors in Morehead City for garage door and handyman work on the Crystal Coast.',
                breadcrumbs: [['name' => 'Contact', 'url' => route('contact')]],
            ),
        ]);
    }
}
