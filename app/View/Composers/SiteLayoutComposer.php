<?php

namespace App\View\Composers;

use App\Models\Service;
use App\Models\ServiceArea;
use App\Settings\SiteSettings;
use Illuminate\View\View;

/**
 * Business details, services, and towns for the public layout's header, footer, and JSON-LD.
 */
class SiteLayoutComposer
{
    public function __construct(private readonly SiteSettings $settings) {}

    public function compose(View $view): void
    {
        $view->with([
            'site' => $this->settings,
            'navServices' => Service::query()->published()->ordered()->get(['name', 'slug']),
            'navAreas' => ServiceArea::query()->published()->ordered()->get(['name', 'slug', 'county']),
        ]);
    }
}
