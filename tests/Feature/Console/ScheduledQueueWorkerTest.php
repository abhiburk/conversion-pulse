<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

function scheduledQueueWorker(): Event
{
    return collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => str_contains($event->command, 'queue:work'));
}

test('the scheduler drains the queue every minute when the scheduled worker is enabled', function () {
    config(['tracking.processing.scheduled_worker' => true]);

    $worker = scheduledQueueWorker();

    expect($worker->expression)->toBe('* * * * *')
        ->and($worker->command)->toEndWith("'artisan' queue:work --stop-when-empty --max-time=50")
        ->and($worker->filtersPass(app()))->toBeTrue();
});

test('the scheduler leaves the queue to a dedicated worker by default', function () {
    expect(scheduledQueueWorker()->filtersPass(app()))->toBeFalse();
});
