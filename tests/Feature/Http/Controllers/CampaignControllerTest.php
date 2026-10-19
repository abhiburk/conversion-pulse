<?php

use App\Enums\CampaignStatus;
use App\Enums\EventType;
use App\Models\Campaign;
use App\Models\EventRollup;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

describe('index', function () {
    test('only campaigns from the current workspace are listed with their site', function () {
        $user = User::factory()->withWorkspace()->create();
        $site = Site::factory()->for($user->currentWorkspace)->create(['name' => 'Storefront']);
        Campaign::factory()->for($site)->create(['name' => 'Exit intent']);
        Campaign::factory()->create(['name' => 'Someone else\'s campaign']);

        $response = $this->actingAs($user)->get(route('campaigns.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/Index')
            ->has('campaigns', 1)
            ->where('campaigns.0.name', 'Exit intent')
            ->where('campaigns.0.site.name', 'Storefront')
        );
    });
});

describe('create', function () {
    test('only sites from the current workspace can be chosen', function () {
        $user = User::factory()->withWorkspace()->create();
        $site = Site::factory()->for($user->currentWorkspace)->create();
        Site::factory()->create();

        $response = $this->actingAs($user)->get(route('campaigns.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/Create')
            ->has('sites', 1)
            ->where('sites.0.id', $site->id)
        );
    });
});

describe('store', function () {
    test('a campaign is created in the current workspace with a public ID', function () {
        $user = User::factory()->withWorkspace()->create();
        $site = Site::factory()->for($user->currentWorkspace)->create();

        $response = $this->actingAs($user)->post(route('campaigns.store'), [
            'site_id' => $site->id,
            'name' => 'Exit intent',
            'status' => 'active',
        ]);

        $campaign = Campaign::sole();
        $response->assertRedirect(route('campaigns.edit', $campaign));
        expect($campaign->workspace_id)->toBe($user->current_workspace_id);
        expect($campaign->site_id)->toBe($site->id);
        expect($campaign->status)->toBe(CampaignStatus::Active);
        expect(Str::isUlid($campaign->public_id))->toBeTrue();
    });

    test('a site from another workspace is rejected', function () {
        $user = User::factory()->withWorkspace()->create();
        $otherSite = Site::factory()->create();

        $response = $this->actingAs($user)->post(route('campaigns.store'), [
            'site_id' => $otherSite->id,
            'name' => 'Exit intent',
            'status' => 'draft',
        ]);

        $response->assertSessionHasErrors(['site_id' => 'Choose one of your sites.']);
        $this->assertDatabaseCount('campaigns', 0);
    });

    test('an unknown status is rejected', function () {
        $user = User::factory()->withWorkspace()->create();
        $site = Site::factory()->for($user->currentWorkspace)->create();

        $response = $this->actingAs($user)->post(route('campaigns.store'), [
            'site_id' => $site->id,
            'name' => 'Exit intent',
            'status' => 'deleted',
        ]);

        $response->assertSessionHasErrors(['status' => 'The selected status is invalid.']);
        $this->assertDatabaseCount('campaigns', 0);
    });

    test('site, name and status are required', function () {
        $user = User::factory()->withWorkspace()->create();

        $response = $this->actingAs($user)->post(route('campaigns.store'), []);

        $response->assertSessionHasErrors([
            'site_id' => 'The site id field is required.',
            'name' => 'The name field is required.',
            'status' => 'The status field is required.',
        ]);
    });
});

describe('show', function () {
    test('the campaign page shows analytics for that campaign only', function () {
        $this->travelTo('2026-10-19 14:00:00');
        $user = User::factory()->withWorkspace()->create();
        $site = Site::factory()->for($user->currentWorkspace)->create();
        $campaign = Campaign::factory()->for($site)->create();
        $sibling = Campaign::factory()->for($site)->create();
        EventRollup::factory()->forCampaign($campaign, EventType::CampaignClick)->create(['bucket_start' => '2026-10-19 13:00:00', 'count' => 3]);
        EventRollup::factory()->forCampaign($sibling, EventType::CampaignClick)->create(['bucket_start' => '2026-10-19 13:00:00', 'count' => 9]);

        $response = $this->actingAs($user)->get(route('campaigns.show', ['campaign' => $campaign, 'range' => '24h']));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/Show')
            ->where('campaign.id', $campaign->id)
            ->where('filters.key', '24h')
            ->where('analytics.totals.clicks', 3)
            ->has('analytics.series', 24)
        );
    });

    test('a campaign from another workspace returns 404', function () {
        $user = User::factory()->withWorkspace()->create();
        $otherCampaign = Campaign::factory()->create();

        $this->actingAs($user)->get(route('campaigns.show', $otherCampaign))->assertNotFound();
    });
});

describe('edit', function () {
    test('the edit page shows the campaign ID', function () {
        $user = User::factory()->withWorkspace()->create();
        $campaign = Campaign::factory()
            ->for(Site::factory()->for($user->currentWorkspace))
            ->create();

        $response = $this->actingAs($user)->get(route('campaigns.edit', $campaign));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/Edit')
            ->where('campaign.public_id', $campaign->public_id)
        );
    });

    test('a campaign from another workspace returns 404', function () {
        $user = User::factory()->withWorkspace()->create();
        $otherCampaign = Campaign::factory()->create();

        $this->actingAs($user)->get(route('campaigns.edit', $otherCampaign))->assertNotFound();
    });
});

describe('update', function () {
    test('name and status are updated but the site cannot be changed', function () {
        $user = User::factory()->withWorkspace()->create();
        $site = Site::factory()->for($user->currentWorkspace)->create();
        $anotherSite = Site::factory()->for($user->currentWorkspace)->create();
        $campaign = Campaign::factory()->for($site)->create();

        $response = $this->actingAs($user)->put(route('campaigns.update', $campaign), [
            'site_id' => $anotherSite->id,
            'name' => 'Renamed',
            'status' => 'paused',
        ]);

        $response->assertRedirect(route('campaigns.edit', $campaign));
        $campaign->refresh();
        expect($campaign->name)->toBe('Renamed');
        expect($campaign->status)->toBe(CampaignStatus::Paused);
        expect($campaign->site_id)->toBe($site->id);
    });

    test('a campaign from another workspace returns 404 and is unchanged', function () {
        $user = User::factory()->withWorkspace()->create();
        $otherCampaign = Campaign::factory()->create(['name' => 'Original']);

        $response = $this->actingAs($user)->put(route('campaigns.update', $otherCampaign), [
            'name' => 'Hijacked',
            'status' => 'archived',
        ]);

        $response->assertNotFound();
        expect($otherCampaign->fresh()->name)->toBe('Original');
    });
});
