<?php

use Database\Seeders\ContentSeeder;

beforeEach(function () {
    $this->seed(ContentSeeder::class);
});

it('renders the home page in Chromium without JavaScript errors', function () {
    visit('/')
        ->assertSee('Installs, repairs, springs, openers, and wind-rated doors')
        ->assertSee('Garage Door Installation')
        ->assertNoJavaScriptErrors();
});

it('works at phone width, with the call and quote bar', function () {
    visit('/')
        ->resize(390, 844)
        ->assertVisible('nav[aria-label="Quick actions"]')
        ->assertSee('Call now')
        ->assertNoJavaScriptErrors();
});
