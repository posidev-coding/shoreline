<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Policies\MediaPolicy;
use App\Settings\SiteSettings;
use App\View\Composers\SiteLayoutComposer;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use STS\FilamentImpersonate\Events\EnterImpersonation;
use STS\FilamentImpersonate\Events\LeaveImpersonation;

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

        // The activity log's and media library's models live in their packages, out of reach of policy discovery.
        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy(Media::class, MediaPolicy::class);

        // When each person last signed in, for the client's Portal tab. Without touching updated_at.
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                User::query()->whereKey($event->user->getKey())->toBase()->update(['last_signed_in_at' => now()]);
            }
        });

        // Every look at a client's portal through their eyes goes in the activity log.
        Event::listen(EnterImpersonation::class, function (EnterImpersonation $event): void {
            if ($event->impersonated instanceof User && $event->impersonator instanceof User) {
                activity()->causedBy($event->impersonator)->performedOn($event->impersonated)->event('impersonated')
                    ->log("Started viewing the portal as {$event->impersonated->email}");
            }
        });
        Event::listen(LeaveImpersonation::class, function (LeaveImpersonation $event): void {
            if ($event->impersonated instanceof User && $event->impersonator instanceof User) {
                activity()->causedBy($event->impersonator)->performedOn($event->impersonated)->event('impersonated')
                    ->log("Stopped viewing the portal as {$event->impersonated->email}");
            }
        });

        View::composer('components.layouts.site', SiteLayoutComposer::class);
        View::composer('site.*', fn ($view) => $view->with('site', $this->app->make(SiteSettings::class)));
        View::composer(['components.layouts.portal', 'portal.*'], fn ($view) => $view->with('site', $this->app->make(SiteSettings::class)));
    }
}
