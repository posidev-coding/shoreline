<?php

use App\Jobs\SweepQuickBooksChanges;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily, so the environment can still sleep. Prunes quote-form uploads nobody submitted.
Schedule::command('app:prune-uploads')->daily();

// Weekly. Keeps a year of the activity log (activitylog.clean_after_days).
Schedule::command('activitylog:clean --force')->weekly();

// Daily. Keeps the QuickBooks refresh token from lapsing, and checks the connection still works.
Schedule::command('app:quickbooks-refresh')->daily();

// Weekly. Prunes models that say how (QuickBooks sync logs after 30 days).
Schedule::command('model:prune')->weekly();

// Hourly, the most the environment allows while still sleeping. Brings in what changed in
// QuickBooks since the last check.
Schedule::job(new SweepQuickBooksChanges)->hourly();
