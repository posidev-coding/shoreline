<?php

use App\Models\Lead;
use Database\Seeders\ContentSeeder;

/*
 * Photos aren't attached here: the plugin's in-process server doesn't parse the files[] array
 * Livewire uploads, so Livewire stores nothing. Uploads are covered in tests/Feature instead.
 */
it('takes a quote request from start to finish', function () {
    $this->seed(ContentSeeder::class);

    visit('/get-a-quote')
        ->click('label:has(input[value="garage-door-repair"])')
        ->press('Next')
        ->assertSee('Which door?')
        ->click('label:has(input[value="double"])')
        ->click('label:has(input[value="spring"])')
        ->click('label:has(input[value="emergency"])')
        ->press('Next')
        ->assertSee('Photos help')
        ->press('Skip photos')
        ->type('#name', 'Jane Smith')
        ->type('#phone', '252-555-0142')
        ->type('#email', 'jane@example.com')
        ->type('#street', '12 Front St')
        ->select('#town', 'beaufort')
        ->type('#zip', '28516')
        ->press('Next')
        ->assertSee('Look it over')
        // The honeypot turns away a form sent sooner after loading than a person could fill it in.
        ->wait(config('honeypot.amount_of_seconds') + 0.5)
        ->press('Send my request')
        ->assertSee('Got it. Thanks!')
        ->assertNoJavaScriptErrors();

    expect(Lead::query()->sole())
        ->email->toBe('jane@example.com')
        ->phone->toBe('+12525550142');
});

it('shows what’s missing instead of moving on', function () {
    $this->seed(ContentSeeder::class);

    visit('/get-a-quote')
        ->press('Next')
        ->assertSee('Choose what you need, or “Something else”.')
        ->assertNoJavaScriptErrors();
});
