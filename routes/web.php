<?php

use App\Http\Controllers\Site\QuoteController;
use Illuminate\Support\Facades\Route;

// Full web stack (session, CSRF): the quote request (M2) and, later, the portal.
Route::get('/get-a-quote', QuoteController::class)->name('quote');
