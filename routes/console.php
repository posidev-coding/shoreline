<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily, so the environment can still sleep. Prunes quote-form uploads nobody submitted.
Schedule::command('app:prune-uploads')->daily();
