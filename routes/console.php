<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Refunds that the gateway refused or could not take are tried again every hour.
Schedule::command('payments:retry-refunds')->hourly()->withoutOverlapping();

// A copy of the database every night; the newest 14 are kept.
Schedule::command('db:backup')->dailyAt('03:00')->withoutOverlapping();
