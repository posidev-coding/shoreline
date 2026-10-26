<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Settings\SiteSettings;
use App\Support\HurricaneReadiness;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Interim quote page until M2's wizard: it takes the same prefill (a service, or the
 * hurricane quiz answers) and turns it into a ready-to-send email.
 */
class QuoteController extends Controller
{
    public function __invoke(Request $request, SiteSettings $settings): View
    {
        $readiness = HurricaneReadiness::fromInput($request->query());
        $service = Service::query()->published()->where('slug', $request->query('service'))->first();

        $subject = 'Quote request'.($service ? ": {$service->name}" : '');
        $lines = ['Name:', 'Address or town:', 'Best phone:', '', 'What do you need?', ''];

        if ($readiness->hasResult()) {
            $lines = [...$lines, 'My hurricane-ready check:', ...$readiness->summaryLines(), ''];
        }

        return view('site.quote', [
            'service' => $service,
            'readiness' => $readiness,
            'mailto' => 'mailto:'.$settings->email.'?'.http_build_query(
                ['subject' => $subject, 'body' => implode("\n", $lines)],
                encoding_type: PHP_QUERY_RFC3986,
            ),
            'seo' => new Seo(
                title: 'Get a Quote',
                description: 'Ask for a quote on garage door installation, repair, or handyman work on the Crystal Coast.',
                canonical: route('quote'),
                breadcrumbs: [['name' => 'Get a Quote', 'url' => route('quote')]],
            ),
        ]);
    }
}
