<?php

use App\Http\Controllers\Site\FaqController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\HurricaneReadyController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\QuoteController;
use App\Http\Controllers\Site\ReviewController;
use App\Http\Controllers\Site\RobotsController;
use App\Http\Controllers\Site\ServiceAreaController;
use App\Http\Controllers\Site\ServiceController;
use App\Http\Controllers\Site\ShowcaseController;
use App\Http\Controllers\Site\SitemapController;
use Illuminate\Support\Facades\Route;

/*
| Edge-cached public pages (the `content` middleware group in bootstrap/app.php).
| No session here: no auth(), session(), old(), $errors, or csrf_token() in these views.
*/

Route::get('/', HomeController::class)->name('home');

Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
Route::get('/services/{service}', [ServiceController::class, 'show'])->name('services.show');

Route::get('/our-work', [ShowcaseController::class, 'index'])->name('work.index');
Route::get('/our-work/{showcase}', [ShowcaseController::class, 'show'])->name('work.show');

Route::get('/service-areas', [ServiceAreaController::class, 'index'])->name('areas.index');
Route::get('/service-areas/{serviceArea}', [ServiceAreaController::class, 'show'])->name('areas.show');

Route::get('/reviews', ReviewController::class)->name('reviews');
Route::get('/faq', FaqController::class)->name('faq');
Route::get('/hurricane-ready', HurricaneReadyController::class)->name('pages.hurricane-ready');

Route::get('/about', PageController::class)->defaults('key', 'about')->name('pages.about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::get('/privacy', PageController::class)->defaults('key', 'privacy')->name('pages.privacy');
Route::get('/terms', PageController::class)->defaults('key', 'terms')->name('pages.terms');

Route::get('/get-a-quote/thanks', [QuoteController::class, 'thanks'])->name('quote.thanks');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');
