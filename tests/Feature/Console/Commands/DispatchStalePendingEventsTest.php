<?php

use App\Enums\EventStatus;
use App\Jobs\ProcessIngestedEvent;
use App\Models\IngestedEvent;
use Illuminate\Support\Facades\Queue;

test('pending events that were never queued or whose job went missing are queued again', function () {
    Queue::fake();
    $neverQueued = IngestedEvent::factory()->create(['dispatched_at' => null]);
    $lostJob = IngestedEvent::factory()->create(['dispatched_at' => now()->subMinutes(11)]);
    IngestedEvent::factory()->create(['dispatched_at' => now()->subMinutes(2)]);
    IngestedEvent::factory()->create(['status' => EventStatus::Processed, 'dispatched_at' => null]);
    IngestedEvent::factory()->create(['status' => EventStatus::Failed, 'dispatched_at' => null]);

    $this->artisan('events:dispatch-stale')
        ->expectsOutputToContain('Re-queued 2 stale pending events.')
        ->assertSuccessful();

    Queue::assertPushed(ProcessIngestedEvent::class, 2);
    Queue::assertPushed(ProcessIngestedEvent::class, fn ($job) => $job->ingestedEventId === $neverQueued->id);
    Queue::assertPushed(ProcessIngestedEvent::class, fn ($job) => $job->ingestedEventId === $lostJob->id);
    expect($neverQueued->fresh()->dispatched_at)->not->toBeNull();
});
