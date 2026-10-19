<?php

use App\Actions\SummarizeEvents;
use App\Enums\EventType;
use App\Models\Campaign;
use App\Models\EventRollup;
use App\Models\Site;
use App\Support\DateRange;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->now = CarbonImmutable::parse('2026-10-19 14:35:00', 'UTC');
    $this->travelTo($this->now);
});

test('hourly counts are summed into zero-filled daily buckets', function () {
    $campaign = Campaign::factory()->create();
    EventRollup::factory()->forCampaign($campaign, EventType::CampaignImpression)->createMany([
        ['bucket_start' => '2026-10-19 09:00:00', 'count' => 3],
        ['bucket_start' => '2026-10-19 13:00:00', 'count' => 4],
        ['bucket_start' => '2026-10-17 10:00:00', 'count' => 5],
    ]);

    $summary = app(SummarizeEvents::class)->handle($campaign->workspace, DateRange::preset('7d', $this->now));

    expect($summary['series'])->toHaveCount(7);
    expect(collect($summary['series'])->pluck('impressions', 'bucket')->all())->toBe([
        '2026-10-13T00:00:00+00:00' => 0,
        '2026-10-14T00:00:00+00:00' => 0,
        '2026-10-15T00:00:00+00:00' => 0,
        '2026-10-16T00:00:00+00:00' => 0,
        '2026-10-17T00:00:00+00:00' => 5,
        '2026-10-18T00:00:00+00:00' => 0,
        '2026-10-19T00:00:00+00:00' => 7,
    ]);
    expect($summary['totals']['impressions'])->toBe(12);
});

test('rates are relative to impressions and ignore conversions without a campaign', function () {
    $campaign = Campaign::factory()->create();
    $rollups = EventRollup::factory()->state(['bucket_start' => '2026-10-19 10:00:00']);
    $rollups->forCampaign($campaign, EventType::CampaignImpression)->create(['count' => 200]);
    $rollups->forCampaign($campaign, EventType::CampaignClick)->create(['count' => 30]);
    $rollups->forCampaign($campaign, EventType::Conversion)->create(['count' => 10]);
    $rollups->for($campaign->site)->create(['type' => EventType::Conversion, 'count' => 50]);

    $totals = app(SummarizeEvents::class)->handle($campaign->workspace, DateRange::preset('7d', $this->now))['totals'];

    expect($totals['conversions'])->toBe(60);
    expect($totals['attributed_conversions'])->toBe(10);
    expect($totals['click_through_rate'])->toBe(0.15);
    expect($totals['conversion_rate'])->toBe(0.05);
});

test('rates are null when there were no impressions', function () {
    $site = Site::factory()->create();

    $totals = app(SummarizeEvents::class)->handle($site->workspace, DateRange::preset('7d', $this->now))['totals'];

    expect($totals['click_through_rate'])->toBeNull();
    expect($totals['conversion_rate'])->toBeNull();
});

test('the previous period is totalled separately for comparison', function () {
    $campaign = Campaign::factory()->create();
    $impressions = EventRollup::factory()->forCampaign($campaign, EventType::CampaignImpression);
    $impressions->create(['bucket_start' => '2026-10-18 10:00:00', 'count' => 8]);
    $impressions->create(['bucket_start' => '2026-10-10 10:00:00', 'count' => 5]);
    $impressions->create(['bucket_start' => '2026-10-01 10:00:00', 'count' => 100]);

    $summary = app(SummarizeEvents::class)->handle($campaign->workspace, DateRange::preset('7d', $this->now));

    expect($summary['totals']['impressions'])->toBe(8);
    expect($summary['previous']['impressions'])->toBe(5);
});

test('only the requested campaign and workspace are counted', function () {
    $campaign = Campaign::factory()->create();
    $sibling = Campaign::factory()->for($campaign->site)->create();
    $rollups = EventRollup::factory()->state(['bucket_start' => '2026-10-19 10:00:00']);
    $rollups->forCampaign($campaign, EventType::CampaignImpression)->create(['count' => 4]);
    $rollups->forCampaign($sibling, EventType::CampaignImpression)->create(['count' => 6]);
    $rollups->forCampaign(Campaign::factory()->create(), EventType::CampaignImpression)->create(['count' => 50]);
    $range = DateRange::preset('7d', $this->now);

    expect(app(SummarizeEvents::class)->handle($campaign->workspace, $range)['totals']['impressions'])->toBe(10);
    expect(app(SummarizeEvents::class)->handle($campaign->workspace, $range, $campaign)['totals']['impressions'])->toBe(4);
});
