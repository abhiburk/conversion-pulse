<?php

use App\Actions\EvaluateAlerts;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\CampaignStatus;
use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\Alert;
use App\Models\Campaign;
use App\Models\EventRollup;
use App\Models\IngestedEvent;
use App\Models\Site;
use App\Models\User;
use App\Notifications\AlertOpened;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->now = CarbonImmutable::parse('2026-10-19 14:20:00', 'UTC');
    $this->travelTo($this->now);
});

/**
 * Record the same number of campaign impressions at noon on each of the previous seven days, and the given count today (inside the checked 11:00–14:00 window).
 */
function impressionsBaseline(Campaign $campaign, int $perDay, int $today): void
{
    $rollups = EventRollup::factory()->forCampaign($campaign, EventType::CampaignImpression);

    foreach (range(1, 7) as $daysAgo) {
        $rollups->create(['bucket_start' => CarbonImmutable::parse('2026-10-19 12:00:00')->subDays($daysAgo), 'count' => $perDay]);
    }

    if ($today > 0) {
        $rollups->create(['bucket_start' => '2026-10-19 12:00:00', 'count' => $today]);
    }
}

function evaluateAlerts(): array
{
    return app(EvaluateAlerts::class)->handle(CarbonImmutable::now());
}

describe('traffic drops', function () {
    test('a sharp drop in an active campaign\'s impressions opens an alert and notifies the workspace', function () {
        $owner = User::factory()->withWorkspace()->create();
        $campaign = Campaign::factory()->for(Site::factory()->for($owner->currentWorkspace))->active()->create();
        impressionsBaseline($campaign, perDay: 40, today: 6);

        $result = evaluateAlerts();

        expect($result['opened'])->toBe(1);
        $alert = Alert::sole();
        expect($alert->type)->toBe(AlertType::VolumeDrop);
        expect($alert->campaign_id)->toBe($campaign->id);
        expect($alert->context)->toMatchArray(['metric' => 'impressions', 'observed' => 6, 'expected' => 40, 'drop' => 0.85]);
        expect($alert->summary())->toBe('Impressions dropped 85%: 6 in the last 3 hours, against 40.0 expected from previous days.');
        Notification::assertSentTo($owner, AlertOpened::class, fn (AlertOpened $notification) => $notification->alert->is($alert));
    });

    test('a drop within the threshold does not open an alert', function () {
        $campaign = Campaign::factory()->active()->create();
        impressionsBaseline($campaign, perDay: 40, today: 21);

        evaluateAlerts();

        expect(Alert::count())->toBe(0);
    });

    test('low-traffic subjects are not monitored', function () {
        $campaign = Campaign::factory()->active()->create();
        impressionsBaseline($campaign, perDay: 10, today: 0);

        evaluateAlerts();

        expect(Alert::count())->toBe(0);
    });

    test('campaigns that are not active are not monitored', function () {
        $campaign = Campaign::factory()->create(['status' => CampaignStatus::Paused]);
        impressionsBaseline($campaign, perDay: 40, today: 0);

        evaluateAlerts();

        expect(Alert::count())->toBe(0);
    });

    test('a drop in site page views opens a site-level alert', function () {
        $site = Site::factory()->create();
        foreach (range(1, 7) as $daysAgo) {
            EventRollup::factory()->for($site)->create(['bucket_start' => CarbonImmutable::parse('2026-10-19 11:00:00')->subDays($daysAgo), 'count' => 100]);
        }

        evaluateAlerts();

        $alert = Alert::sole();
        expect($alert->campaign_id)->toBeNull();
        expect($alert->context['metric'])->toBe('page_views');
        expect($alert->context['observed'])->toBe(0);
    });
});

describe('alert lifecycle', function () {
    test('an ongoing problem refreshes the open alert without duplicating it or notifying again', function () {
        $owner = User::factory()->withWorkspace()->create();
        $campaign = Campaign::factory()->for(Site::factory()->for($owner->currentWorkspace))->active()->create();
        impressionsBaseline($campaign, perDay: 40, today: 6);
        evaluateAlerts();
        EventRollup::factory()->forCampaign($campaign, EventType::CampaignImpression)->create(['bucket_start' => '2026-10-19 13:00:00', 'count' => 2]);
        $this->travel(15)->minutes();

        $result = evaluateAlerts();

        expect($result)->toBe(['opened' => 0, 'resolved' => 0, 'ongoing' => 1]);
        $alert = Alert::sole();
        expect($alert->context['observed'])->toBe(8);
        expect($alert->last_evaluated_at->toDateTimeString())->toBe('2026-10-19 14:35:00');
        Notification::assertSentToTimes($owner, AlertOpened::class, 1);
    });

    test('an alert resolves itself once traffic recovers', function () {
        $campaign = Campaign::factory()->active()->create();
        impressionsBaseline($campaign, perDay: 40, today: 6);
        evaluateAlerts();
        EventRollup::factory()->forCampaign($campaign, EventType::CampaignImpression)->create(['bucket_start' => '2026-10-19 13:00:00', 'count' => 30]);

        $result = evaluateAlerts();

        expect($result['resolved'])->toBe(1);
        $alert = Alert::sole();
        expect($alert->status)->toBe(AlertStatus::Resolved);
        expect($alert->resolved_at)->not->toBeNull();
    });

    test('pausing a campaign resolves its traffic alert', function () {
        $campaign = Campaign::factory()->active()->create();
        impressionsBaseline($campaign, perDay: 40, today: 0);
        evaluateAlerts();
        $campaign->update(['status' => CampaignStatus::Paused]);

        evaluateAlerts();

        expect(Alert::sole()->status)->toBe(AlertStatus::Resolved);
    });

    test('a problem that returns after resolving opens a new alert', function () {
        $campaign = Campaign::factory()->active()->create();
        Alert::factory()->resolved()->create([
            'workspace_id' => $campaign->workspace_id,
            'site_id' => $campaign->site_id,
            'campaign_id' => $campaign->id,
            'campaign_key' => $campaign->id,
            'type' => AlertType::VolumeDrop,
        ]);
        impressionsBaseline($campaign, perDay: 40, today: 0);

        evaluateAlerts();

        expect(Alert::where('status', AlertStatus::Open)->count())->toBe(1);
        expect(Alert::count())->toBe(2);
    });

    test('the database allows only one open alert per subject and type', function () {
        $alert = Alert::factory()->create();

        expect(fn () => Alert::factory()->for($alert->site)->create(['type' => $alert->type]))
            ->toThrow(UniqueConstraintViolationException::class);
    });
});

describe('processing problems', function () {
    test('repeated recent processing failures open an alert for the site', function () {
        config(['tracking.alerts.failure_threshold' => 3]);
        $site = Site::factory()->create();
        IngestedEvent::factory()->for($site)->count(3)->create(['status' => EventStatus::Failed, 'failed_at' => now()->subMinutes(20)]);
        IngestedEvent::factory()->for($site)->count(5)->create(['status' => EventStatus::Failed, 'failed_at' => now()->subHours(3)]);

        evaluateAlerts();

        $alert = Alert::sole();
        expect($alert->type)->toBe(AlertType::ProcessingFailures);
        expect($alert->summary())->toBe('3 events failed processing in the last 60 minutes.');
    });

    test('failures below the threshold do not open an alert', function () {
        config(['tracking.alerts.failure_threshold' => 3]);
        IngestedEvent::factory()->count(2)->create(['status' => EventStatus::Failed, 'failed_at' => now()->subMinutes(5)]);

        evaluateAlerts();

        expect(Alert::count())->toBe(0);
    });

    test('events left pending too long open a stalled processing alert', function () {
        $site = Site::factory()->create();
        IngestedEvent::factory()->for($site)->create(['received_at' => now()->subMinutes(16)]);
        IngestedEvent::factory()->for($site)->create(['received_at' => now()->subMinutes(2)]);

        evaluateAlerts();

        $alert = Alert::sole();
        expect($alert->type)->toBe(AlertType::ProcessingStalled);
        expect($alert->context['pending'])->toBe(1);
    });
});
