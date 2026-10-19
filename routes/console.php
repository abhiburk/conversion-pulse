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

Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->when(fn (): bool => config('tracking.processing.scheduled_worker'));

// Reset the database every 1 hour
Schedule::command('migrate:fresh --seed')->hourly();