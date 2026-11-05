<?php

use App\Http\Controllers\QuickBooksWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Webhooks from other services (bootstrap/app.php loads this with no middleware). No session,
| cookies, or CSRF: each sender signs its requests, and the controller checks the signature
| before anything else.
*/

Route::post('/webhooks/quickbooks', QuickBooksWebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('webhooks.quickbooks');
