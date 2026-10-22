<?php

namespace App\Providers;

use App\Settings\SiteSettings;
use App\View\Composers\SiteLayoutComposer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Surface N+1 queries in development and tests instead of in production.
        Model::preventLazyLoading(! $this->app->isProduction());

        View::composer('components.layouts.site', SiteLayoutComposer::class);
        View::composer('site.*', fn ($view) => $view->with('site', $this->app->make(SiteSettings::class)));
    }
}
