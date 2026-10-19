<?php

use App\Actions\ProcessEvent;
use App\Enums\CampaignStatus;
use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Jobs\ProcessIngestedEvent;
use App\Models\Campaign;
use App\Models\EventRollup;
use App\Models\IngestedEvent;

test('a rejected event is marked failed immediately without automatic retries', function () {
    $campaign = Campaign::factory()->create(['status' => CampaignStatus::Archived]);
    $event = IngestedEvent::factory()->for($campaign->site)->create([
        'campaign_id' => $campaign->id,
        'type' => EventType::CampaignImpression,
    ]);

    ProcessIngestedEvent::dispatchSync($event->id);

    $event->refresh();
    expect($event->status)->toBe(EventStatus::Failed);
    expect($event->attempts)->toBe(1);
    expect($event->last_error)->toStartWith('The campaign is archived.');
    expect($event->failed_at)->not->toBeNull();
});

test('a transient error records the attempt and is rethrown so the queue retries', function () {
    $event = IngestedEvent::factory()->create();
    $processEvent = Mockery::mock(ProcessEvent::class);
    $processEvent->shouldReceive('handle')->andThrow(new RuntimeException('Database unavailable'));

    expect(fn () => (new ProcessIngestedEvent($event->id))->handle($processEvent))
        ->toThrow(RuntimeException::class, 'Database unavailable');

    $event->refresh();
    expect($event->status)->toBe(EventStatus::Pending);
    expect($event->attempts)->toBe(1);
    expect($event->last_error)->toBe('Database unavailable');
});

test('the event is marked failed once the queue gives up', function () {
    $event = IngestedEvent::factory()->create(['attempts' => 3]);

    (new ProcessIngestedEvent($event->id))->failed(new RuntimeException('Database unavailable'));

    $event->refresh();
    expect($event->status)->toBe(EventStatus::Failed);
    expect($event->last_error)->toBe('Database unavailable');
    expect($event->failed_at)->not->toBeNull();
});

test('a late failure notice does not undo an event that was processed', function () {
    $event = IngestedEvent::factory()->create();
    ProcessIngestedEvent::dispatchSync($event->id);

    (new ProcessIngestedEvent($event->id))->failed(new RuntimeException('Timed out'));

    expect($event->fresh()->status)->toBe(EventStatus::Processed);
    expect(EventRollup::sole()->count)->toBe(1);
});
