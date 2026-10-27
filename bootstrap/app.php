<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('content')->group(base_path('routes/content.php'));
            Route::group([], base_path('routes/feeds.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Public content pages run without session, cookies, or CSRF, and say they're cacheable,
        // so Laravel Cloud's edge serves them without waking the app. Any Set-Cookie would
        // stop the edge caching, so nothing in this group may start a session.
        $middleware->group('content', [
            SubstituteBindings::class,
            'cache.headers:public;s_maxage=600;stale_while_revalidate=86400;etag',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
