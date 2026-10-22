<?php

use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Vite;

beforeEach(function () {
    $this->seed(ContentSeeder::class);
});

it('marks content pages cacheable at the edge and sets no cookies', function (string $url) {
    $response = $this->get($url)->assertOk();

    expect($response->headers->get('Cache-Control'))->toBe('public, s-maxage=600, stale-while-revalidate=86400')
        ->and($response->headers->has('ETag'))->toBeTrue()
        // Any Set-Cookie stops Laravel Cloud's edge from caching the page.
        ->and($response->headers->getCookies())->toBeEmpty();
})->with([
    '/', '/services', '/services/garage-door-repair', '/our-work', '/service-areas', '/service-areas/beaufort',
    '/reviews', '/faq', '/hurricane-ready', '/about', '/contact', '/privacy', '/terms', '/sitemap.xml', '/robots.txt',
]);

it('answers a matching ETag with 304 Not Modified', function () {
    $etag = $this->get('/faq')->headers->get('ETag');

    // Vite remembers which fonts and assets it already preloaded, and two requests in one
    // test share the app. Reset it, as each real request (and Octane) starts fresh.
    app(Vite::class)->flush();

    $this->get('/faq', ['If-None-Match' => $etag])->assertStatus(304);
});

it('keeps the quote page and the admin out of the edge cache', function (string $url) {
    $response = $this->get($url)->assertOk();

    expect($response->headers->get('Cache-Control'))->not->toContain('public')
        ->and($response->headers->getCookies())->not->toBeEmpty();
})->with(['/get-a-quote', '/admin/login']);
