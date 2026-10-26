<?php

use Database\Seeders\ContentSeeder;

/**
 * Picks an option by its form name and value. (The plugin reads text with a comma,
 * like "Yes, regular glass", as a CSS selector, so text can't select every option.)
 */
function answer(string $question, string $option): string
{
    return "label:has(input[name=\"{$question}\"][value=\"{$option}\"])";
}

it('takes the hurricane check through to a result', function () {
    $this->seed(ContentSeeder::class);

    visit('/hurricane-ready')
        ->click(answer('age', 'over-20'))
        ->click(answer('windows', 'standard'))
        ->click(answer('condition', 'some'))
        ->press('See my result')
        ->assertSee('Have someone look at it before the next storm.')
        ->assertSee('Your hurricane-ready answers come with this request')
        ->assertNoJavaScriptErrors();
});
