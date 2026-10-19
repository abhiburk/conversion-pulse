<?php

use App\Enums\EventStatus;
use App\Jobs\ProcessIngestedEvent;
use App\Models\IngestedEvent;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

describe('store', function () {
    test('a failed event is moved back to pending and queued', function () {
        $user = User::factory()->withWorkspace()->create();
        $event = IngestedEvent::factory()
            ->for(Site::factory()->for($user->currentWorkspace))
            ->create(['status' => EventStatus::Failed, 'failed_at' => now()]);

        $response = $this->actingAs($user)->post(route('failed-events.retry', $event));

        $response->assertRedirect(route('pipeline.index'));
        $event->refresh();
        expect($event->status)->toBe(EventStatus::Pending);
        expect($event->failed_at)->toBeNull();
        Queue::assertPushed(ProcessIngestedEvent::class, fn ($job) => $job->ingestedEventId === $event->id);
    });

    test('retrying an event that is no longer failed queues nothing', function () {
        $user = User::factory()->withWorkspace()->create();
        $event = IngestedEvent::factory()
            ->for(Site::factory()->for($user->currentWorkspace))
            ->create(['status' => EventStatus::Processed]);

        $this->actingAs($user)->post(route('failed-events.retry', $event));

        expect($event->fresh()->status)->toBe(EventStatus::Processed);
        Queue::assertNothingPushed();
    });

    test('an event from another workspace returns 404 and is unchanged', function () {
        $user = User::factory()->withWorkspace()->create();
        $otherEvent = IngestedEvent::factory()->create(['status' => EventStatus::Failed]);

        $response = $this->actingAs($user)->post(route('failed-events.retry', $otherEvent));

        $response->assertNotFound();
        expect($otherEvent->fresh()->status)->toBe(EventStatus::Failed);
        Queue::assertNothingPushed();
    });
});

describe('storeAll', function () {
    test('every failed event in the current workspace is queued and other workspaces are untouched', function () {
        $user = User::factory()->withWorkspace()->create();
        $site = Site::factory()->for($user->currentWorkspace)->create();
        IngestedEvent::factory()->for($site)->count(2)->create(['status' => EventStatus::Failed]);
        IngestedEvent::factory()->for($site)->create(['status' => EventStatus::Processed]);
        $otherEvent = IngestedEvent::factory()->create(['status' => EventStatus::Failed]);

        $response = $this->actingAs($user)->post(route('failed-events.retry-all'));

        $response->assertRedirect(route('pipeline.index'));
        Queue::assertPushed(ProcessIngestedEvent::class, 2);
        expect($site->workspace->ingestedEvents()->where('status', EventStatus::Failed)->count())->toBe(0);
        expect($otherEvent->fresh()->status)->toBe(EventStatus::Failed);
    });
});
