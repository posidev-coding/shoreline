<?php

it('renders the home page in Chromium without JavaScript errors', function () {
    visit('/')
        ->assertSee("Let's get started")
        ->assertNoJavaScriptErrors();
});
