<?php

use App\Enums\EventType;
use App\Models\Campaign;
use App\Models\EventRollup;
use App\Models\Site;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the dashboard shows the last 7 days by default', function () {
    $this->travelTo('2026-10-19 14:00:00');
    $user = User::factory()->withWorkspace()->create();
    $campaign = Campaign::factory()->for(Site::factory()->for($user->currentWorkspace))->active()->create();
    EventRollup::factory()->forCampaign($campaign, EventType::CampaignImpression)->create([
        'bucket_start' => '2026-10-19 09:00:00',
        'count' => 42,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->where('filters.key', '7d')
        ->where('filters.granularity', 'day')
        ->where('analytics.totals.impressions', 42)
        ->has('analytics.series', 7)
        ->where('campaigns.0.id', $campaign->id)
        ->where('hasSites', true)
    );
});

test('a custom range of up to two days is charted per hour', function () {
    $this->travelTo('2026-10-19 14:00:00');
    $user = User::factory()->withWorkspace()->create();

    $response = $this->actingAs($user)->get(route('dashboard', ['range' => 'custom', 'from' => '2026-10-18', 'to' => '2026-10-19']));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('filters.key', 'custom')
        ->where('filters.granularity', 'hour')
        ->has('analytics.series', 48)
    );
});

test('invalid ranges are rejected with a message', function (array $query, string $field, string $message) {
    $this->travelTo('2026-10-19 14:00:00');
    $user = User::factory()->withWorkspace()->create();

    $response = $this->actingAs($user)->get(route('dashboard', $query));

    $response->assertSessionHasErrors([$field => $message]);
})->with([
    'unknown preset' => [['range' => '1y'], 'range', 'The selected range is invalid.'],
    'custom without dates' => [['range' => 'custom'], 'from', 'The from field is required when range is custom.'],
    'end before start' => [['range' => 'custom', 'from' => '2026-10-10', 'to' => '2026-10-01'], 'to', 'The to field must be a date after or equal to from.'],
    'future end' => [['range' => 'custom', 'from' => '2026-10-10', 'to' => '2026-10-25'], 'to', 'The to field must be a date before or equal to today.'],
    'longer than 90 days' => [['range' => 'custom', 'from' => '2026-06-01', 'to' => '2026-10-01'], 'to', 'Choose a range of at most 90 days.'],
]);
