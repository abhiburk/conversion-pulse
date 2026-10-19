<?php

use App\Console\Commands\DispatchStalePendingEvents;
use App\Console\Commands\RunAlertChecks;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(DispatchStalePendingEvents::class)->everyFiveMinutes()->withoutOverlapping();
Schedule::command(RunAlertChecks::class)->everyFifteenMinutes()->withoutOverlapping(30);
