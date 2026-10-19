<?php

use App\Actions\ProcessEvent;
use App\Enums\CampaignStatus;
use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Exceptions\EventRejected;
use App\Models\Campaign;
use App\Models\EventRollup;
use App\Models\IngestedEvent;
use App\Models\Site;

test('a pending event is counted in its hourly bucket and marked processed', function () {
    $campaign = Campaign::factory()->create();
    $event = IngestedEvent::factory()->for($campaign->site)->create([
        'campaign_id' => $campaign->id,
        'type' => EventType::CampaignImpression,
        'occurred_at' => '2026-10-19 10:42:13',
    ]);

    $processed = app(ProcessEvent::class)->handle($event->id);

    expect($processed)->toBeTrue();
    $rollup = EventRollup::sole();
    expect($rollup->campaign_id)->toBe($campaign->id);
    expect($rollup->type)->toBe(EventType::CampaignImpression);
    expect($rollup->bucket_start->toDateTimeString())->toBe('2026-10-19 10:00:00');
    expect($rollup->count)->toBe(1);
    $event->refresh();
    expect($event->status)->toBe(EventStatus::Processed);
    expect($event->attempts)->toBe(1);
    expect($event->processed_at)->not->toBeNull();
});

test('processing the same event again does not count it twice', function () {
    $event = IngestedEvent::factory()->create();
    app(ProcessEvent::class)->handle($event->id);

    $processedAgain = app(ProcessEvent::class)->handle($event->id);

    expect($processedAgain)->toBeFalse();
    expect(EventRollup::sole()->count)->toBe(1);
});

test('events in the same hour share a bucket even without a campaign', function () {
    $site = Site::factory()->create();
    $events = IngestedEvent::factory()->for($site)->count(2)->sequence(
        ['occurred_at' => '2026-10-19 10:05:00'],
        ['occurred_at' => '2026-10-19 10:55:00'],
    )->create(['type' => EventType::PageView, 'campaign_id' => null]);

    $events->each(fn (IngestedEvent $event) => app(ProcessEvent::class)->handle($event->id));

    $rollup = EventRollup::sole();
    expect($rollup->campaign_id)->toBeNull();
    expect($rollup->count)->toBe(2);
});

test('events in different hours are counted in separate buckets', function () {
    $site = Site::factory()->create();
    $events = IngestedEvent::factory()->for($site)->count(2)->sequence(
        ['occurred_at' => '2026-10-19 10:59:59'],
        ['occurred_at' => '2026-10-19 11:00:00'],
    )->create();

    $events->each(fn (IngestedEvent $event) => app(ProcessEvent::class)->handle($event->id));

    expect(EventRollup::orderBy('bucket_start')->pluck('count')->all())->toBe([1, 1]);
});

test('a previously failed event is processed and its error cleared', function () {
    $event = IngestedEvent::factory()->create([
        'status' => EventStatus::Failed,
        'attempts' => 3,
        'last_error' => 'Database unavailable',
        'failed_at' => now(),
    ]);

    app(ProcessEvent::class)->handle($event->id);

    $event->refresh();
    expect($event->status)->toBe(EventStatus::Processed);
    expect($event->attempts)->toBe(4);
    expect($event->last_error)->toBeNull();
    expect($event->failed_at)->toBeNull();
});

test('an event for an archived campaign is rejected and nothing is counted', function () {
    $campaign = Campaign::factory()->create(['status' => CampaignStatus::Archived]);
    $event = IngestedEvent::factory()->for($campaign->site)->create([
        'campaign_id' => $campaign->id,
        'type' => EventType::CampaignClick,
    ]);

    expect(fn () => app(ProcessEvent::class)->handle($event->id))
        ->toThrow(EventRejected::class, 'The campaign is archived.');

    expect(EventRollup::count())->toBe(0);
    $event->refresh();
    expect($event->status)->toBe(EventStatus::Pending);
    expect($event->attempts)->toBe(0);
});
