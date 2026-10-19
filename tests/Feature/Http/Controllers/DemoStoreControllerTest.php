<?php

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Site;
use Database\Seeders\DemoSeeder;

test('the demo store loads the SDK with the demo site key and campaign', function () {
    $site = Site::factory()->create(['site_key' => DemoSeeder::STORE_SITE_KEY]);
    $campaign = Campaign::factory()->for($site)->create(['name' => 'Exit-intent 10% discount', 'status' => CampaignStatus::Active]);

    $response = $this->get(route('demo'));

    $response->assertOk()
        ->assertSee('data-site-key="'.DemoSeeder::STORE_SITE_KEY.'"', escape: false)
        ->assertSee($campaign->public_id);
});

test('the demo store explains how to seed the demo when it is missing', function () {
    $response = $this->get(route('demo'));

    $response->assertOk()
        ->assertSee('Demo data not found')
        ->assertDontSee('sdk/pulse.js');
});
