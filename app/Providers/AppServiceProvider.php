<?php

namespace App\Providers;

use App\Policies\ActivityPolicy;
use App\Settings\SiteSettings;
use App\View\Composers\SiteLayoutComposer;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

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

        FilamentTimezone::set(config('app.business_timezone'));

        // The activity log's model lives in the package, out of reach of policy discovery.
        Gate::policy(Activity::class, ActivityPolicy::class);

        View::composer('components.layouts.site', SiteLayoutComposer::class);
        View::composer('site.*', fn ($view) => $view->with('site', $this->app->make(SiteSettings::class)));
    }
}
