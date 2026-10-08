<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Refunds that the gateway refused or could not take are tried again every hour.
Schedule::command('payments:retry-refunds')->hourly()->withoutOverlapping();
