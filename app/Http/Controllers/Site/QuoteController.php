<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Support\HurricaneReadiness;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Livewire\Livewire;

/**
 * The quote request page. A service page or the hurricane-ready check can prefill it,
 * and the wizard itself (App\Livewire\QuoteWizard) does the rest.
 */
class QuoteController extends Controller
{
    public function __invoke(Request $request): View
    {
        // Livewire's script is the page's heaviest asset; deferred, it no longer holds up the first paint.
        // Only here: the admin's own scripts expect Livewire to have loaded before them.
        Livewire::useScriptTagAttributes(['defer' => true]);

        $service = Service::query()->published()->where('slug', $request->query('service'))->first();

        return view('site.quote', [
            'service' => $service,
            'readiness' => HurricaneReadiness::fromInput($request->query()),
            'seo' => new Seo(
                title: 'Get a Quote',
                description: 'Ask for a quote on garage door installation, repair, or handyman work on the Crystal Coast. Send photos and we’ll get back to you.',
                canonical: route('quote'),
                breadcrumbs: [['name' => 'Get a Quote', 'url' => route('quote')]],
            ),
        ]);
    }

    public function thanks(): View
    {
        return view('site.quote-thanks', [
            'seo' => new Seo(
                title: 'Thanks for your request',
                description: 'We got your quote request.',
                canonical: route('quote.thanks'),
                noindex: true,
            ),
        ]);
    }
}
