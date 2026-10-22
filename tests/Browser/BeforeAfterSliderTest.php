<?php

use App\Models\Showcase;
use Database\Seeders\ContentSeeder;

it('moves the before/after divider from the keyboard', function () {
    $this->seed(ContentSeeder::class);
    $showcase = Showcase::factory()->create();

    visit(route('work.show', $showcase))
        ->assertScript("document.querySelector('.before-after').style.getPropertyValue('--position')", '50%')
        ->keys('[data-before-after]', ['ArrowRight', 'ArrowRight', 'ArrowRight'])
        ->assertScript("document.querySelector('.before-after').style.getPropertyValue('--position')", '53%')
        ->assertNoJavaScriptErrors();
});
