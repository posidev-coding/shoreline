<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Service;
use App\Models\ServiceArea;
use App\Models\Showcase;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;
use Symfony\Component\HttpFoundation\Response;

/**
 * Built from the database on request: Cloud's filesystem is ephemeral, so there's no file to write.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $sitemap = Sitemap::create()
            ->add(Url::create(route('home')))
            ->add(Url::create(route('services.index')))
            ->add(Url::create(route('work.index')))
            ->add(Url::create(route('areas.index')))
            ->add(Url::create(route('reviews')))
            ->add(Url::create(route('faq')))
            ->add(Url::create(route('contact')))
            ->add(Url::create(route('quote')));

        foreach (Page::query()->get() as $page) {
            $sitemap->add(Url::create(route($page->key->routeName()))->setLastModificationDate($page->updated_at ?? now()));
        }

        foreach (Service::query()->published()->ordered()->get() as $service) {
            $sitemap->add(Url::create(route('services.show', $service))->setLastModificationDate($service->updated_at ?? now()));
        }

        foreach (ServiceArea::query()->published()->ordered()->get() as $area) {
            $sitemap->add(Url::create(route('areas.show', $area))->setLastModificationDate($area->updated_at ?? now()));
        }

        foreach (Showcase::query()->published()->ordered()->get() as $showcase) {
            $sitemap->add(Url::create(route('work.show', $showcase))->setLastModificationDate($showcase->updated_at ?? now()));
        }

        return $sitemap->toResponse(request());
    }
}
