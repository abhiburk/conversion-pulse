<?php

use App\Actions\RankCampaigns;
use App\Enums\CampaignStatus;
use App\Enums\EventType;
use App\Models\Campaign;
use App\Models\EventRollup;
use App\Models\Site;
use App\Support\DateRange;
use Carbon\CarbonImmutable;

test('campaigns are ranked by impressions and live campaigns without traffic are kept', function () {
    $now = CarbonImmutable::parse('2026-10-19 14:00:00', 'UTC');
    $this->travelTo($now);
    $site = Site::factory()->create();
    $popular = Campaign::factory()->for($site)->create(['name' => 'Popular', 'status' => CampaignStatus::Paused]);
    $quiet = Campaign::factory()->for($site)->create(['name' => 'Quiet', 'status' => CampaignStatus::Active]);
    Campaign::factory()->for($site)->create(['name' => 'Silent', 'status' => CampaignStatus::Active]);
    Campaign::factory()->for($site)->create(['name' => 'Unused draft', 'status' => CampaignStatus::Draft]);
    $rollups = EventRollup::factory()->state(['bucket_start' => '2026-10-19 10:00:00']);
    $rollups->forCampaign($popular, EventType::CampaignImpression)->create(['count' => 100]);
    $rollups->forCampaign($popular, EventType::CampaignClick)->create(['count' => 25]);
    $rollups->forCampaign($quiet, EventType::CampaignImpression)->create(['count' => 10]);
    $rollups->forCampaign(Campaign::factory()->create(), EventType::CampaignImpression)->create(['count' => 999]);

    $ranking = app(RankCampaigns::class)->handle($site->workspace, DateRange::preset('7d', $now));

    expect(array_column($ranking, 'name'))->toBe(['Popular', 'Quiet', 'Silent']);
    expect($ranking[0]['clicks'])->toBe(25);
    expect($ranking[0]['click_through_rate'])->toBe(0.25);
    expect($ranking[2]['impressions'])->toBe(0);
    expect($ranking[2]['click_through_rate'])->toBeNull();
});
