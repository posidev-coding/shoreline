<?php

use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\ProjectController;
use App\Http\Controllers\Portal\SignInController;
use App\Http\Controllers\Site\QuoteController;
use Illuminate\Support\Facades\Route;

// Full web stack (session, CSRF): the quote wizard (a Livewire form) and the portal.
Route::get('/get-a-quote', QuoteController::class)->name('quote');

// The client portal: signed in with an emailed code, scoped to the clients each person belongs to.
Route::prefix('portal')->name('portal.')->group(function () {
    Route::get('/sign-in', [SignInController::class, 'create'])->name('login');
    Route::get('/sign-in/confirm', [SignInController::class, 'show'])->name('sign-in.confirm');
    Route::post('/sign-in/confirm', [SignInController::class, 'confirm'])->name('sign-in.store');

    Route::middleware('client')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/projects/{ulid}', ProjectController::class)->name('projects.show');
        Route::post('/sign-out', [SignInController::class, 'destroy'])->name('logout');
    });
});
