<?php

use App\Http\Controllers\VisitFeedController;
use Illuminate\Support\Facades\Route;

/*
| Calendar feeds, fetched by calendar apps (bootstrap/app.php loads this with no middleware).
| No session or cookies, and no edge caching: the secret in the URL is the only credential,
| and a replaced link has to stop working at once.
*/

Route::get('/feeds/visits/{token}.ics', VisitFeedController::class)
    ->where('token', '[A-Za-z0-9]{48}')
    ->name('feeds.visits');
