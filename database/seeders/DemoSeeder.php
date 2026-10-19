<?php

namespace Database\Seeders;

use App\Actions\CreateWorkspace;
use App\Actions\EvaluateAlerts;
use App\Enums\CampaignStatus;
use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Jobs\ProcessIngestedEvent;
use App\Models\Campaign;
use App\Models\EventRollup;
use App\Models\IngestedEvent;
use App\Models\Site;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * DEMO FIXTURES. Seeds a sample workspace with 30 days of synthetic tracking data so the dashboard, pipeline and alerts can be explored without a live site.
 *
 * Hourly rollups are generated directly for the full history; raw events are generated for the last 24 hours only, matching those rollups. A few scenarios are staged so the monitoring has something to find:
 * - "Free shipping banner" stops receiving impressions three hours before seeding (a broken install), which raises a traffic-drop alert.
 * - Recent events for the archived "Spring sale popup" go through real processing and fail, which raises a processing-failures alert.
 *
 * The data ends at the time of seeding, so re-run the seeder to refresh it. Running it again replaces the previous demo workspace. It avoids model factories so it also runs on installs without dev dependencies.
 */
class DemoSeeder extends Seeder
{
    public const string EMAIL = 'demo@conversionpulse.test';

    public const string PASSWORD = 'password';

    public const string STORE_SITE_KEY = 'pk_demo_acme_outdoor_store';

    private const int HISTORY_DAYS = 30;

    private CarbonImmutable $now;

    /**
     * @var list<array<string, mixed>>
     */
    private array $rollups = [];

    /**
     * @var list<array<string, mixed>>
     */
    private array $rawEvents = [];

    public function __construct(private CreateWorkspace $createWorkspace, private EvaluateAlerts $evaluateAlerts) {}

    /**
     * Seed the demo workspace.
     */
    public function run(): void
    {
        mt_srand(20261019);
        $this->now = Date::now()->utc();

        $this->removePreviousDemo();

        $user = User::create([
            'name' => 'Demo User',
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ]);
        $user->forceFill(['email_verified_at' => $this->now])->save();
        $workspace = $this->createWorkspace->handle($user, 'Acme Outdoor (demo)');

        $store = $workspace->sites()->forceCreate([
            'workspace_id' => $workspace->id,
            'name' => 'Acme Outdoor store',
            'domain' => $this->appHost(),
            'site_key' => self::STORE_SITE_KEY,
        ]);
        $blog = $workspace->sites()->create(['name' => 'Acme journal', 'domain' => 'journal.acme-outdoor.example']);

        $campaigns = [
            'exit' => $workspace->campaigns()->create(['site_id' => $store->id, 'name' => 'Exit-intent 10% discount', 'status' => CampaignStatus::Active]),
            'shipping' => $workspace->campaigns()->create(['site_id' => $store->id, 'name' => 'Free shipping banner', 'status' => CampaignStatus::Active]),
            'spring' => $workspace->campaigns()->create(['site_id' => $store->id, 'name' => 'Spring sale popup', 'status' => CampaignStatus::Archived]),
            'newsletter' => $workspace->campaigns()->create(['site_id' => $blog->id, 'name' => 'Newsletter signup bar', 'status' => CampaignStatus::Active]),
            'teaser' => $workspace->campaigns()->create(['site_id' => $store->id, 'name' => 'Black Friday teaser', 'status' => CampaignStatus::Draft]),
        ];

        $brokenSince = $this->now->startOfHour()->subHours(3);
        $archivedSince = $this->now->startOfDay()->subDays(12);

        foreach ($this->hours() as $hour => $share) {
            $traffic = $this->trafficLevel($hour) * $share;

            $this->pageViews($store, $hour, 120 * $traffic);
            $this->pageViews($blog, $hour, 55 * $traffic);
            $this->siteConversions($store, $hour, 1.5 * $traffic);

            $this->campaignFunnel($campaigns['exit'], $hour, 32 * $traffic, clickRate: 0.09, conversionRate: 0.3);
            $this->campaignFunnel($campaigns['newsletter'], $hour, 24 * $traffic, clickRate: 0.06, conversionRate: 0.45);

            if ($hour->lessThan($brokenSince)) {
                $this->campaignFunnel($campaigns['shipping'], $hour, 60 * $traffic, clickRate: 0.04, conversionRate: 0.15);
            }

            if ($hour->lessThan($archivedSince)) {
                $this->campaignFunnel($campaigns['spring'], $hour, 30 * $traffic, clickRate: 0.07, conversionRate: 0.25);
            }
        }

        foreach (array_chunk($this->rollups, 500) as $chunk) {
            EventRollup::query()->insert($chunk);
        }

        foreach (array_chunk($this->rawEvents, 500) as $chunk) {
            IngestedEvent::query()->insert($chunk);
        }

        $this->stageFailedEvents($campaigns['spring']);
        $this->evaluateAlerts->handle($this->now);

        $this->command->info('Demo workspace seeded. Sign in as '.self::EMAIL.' / '.self::PASSWORD.' and open /demo to send live events.');
    }

    /**
     * Delete the previous demo user and their workspaces; cascades remove sites, campaigns, events, rollups and alerts.
     */
    private function removePreviousDemo(): void
    {
        $previous = User::where('email', self::EMAIL)->first();

        if ($previous === null) {
            return;
        }

        $previous->workspaces()->each(fn ($workspace) => $workspace->delete());
        $previous->delete();
    }

    /**
     * Every hour of the history up to the current one, with the share of that hour that has elapsed (the current hour is partial).
     *
     * @return \Generator<CarbonImmutable, float>
     */
    private function hours(): \Generator
    {
        $current = $this->now->startOfHour();

        for ($hour = $current->subDays(self::HISTORY_DAYS); $hour->lessThanOrEqualTo($current); $hour = $hour->addHour()) {
            yield $hour => $hour->equalTo($current) ? $this->now->minute / 60 : 1.0;
        }
    }

    /**
     * Relative traffic for an hour: quiet nights, an afternoon peak and slower weekends.
     */
    private function trafficLevel(CarbonImmutable $hour): float
    {
        $daytime = max(0.0, sin(M_PI * ($hour->hour - 6) / 18));
        $weekday = $hour->isWeekend() ? 0.8 : 1.0;

        return (0.2 + 0.8 * $daytime) * $weekday;
    }

    private function pageViews(Site $site, CarbonImmutable $hour, float $expected): void
    {
        $this->record($site, null, EventType::PageView, $hour, $this->vary($expected));
    }

    private function siteConversions(Site $site, CarbonImmutable $hour, float $expected): void
    {
        $this->record($site, null, EventType::Conversion, $hour, $this->vary($expected));
    }

    private function campaignFunnel(Campaign $campaign, CarbonImmutable $hour, float $expectedImpressions, float $clickRate, float $conversionRate): void
    {
        $impressions = $this->vary($expectedImpressions);
        $clicks = $this->vary($impressions * $clickRate);
        $conversions = $this->vary($clicks * $conversionRate);

        $this->record($campaign->site, $campaign, EventType::CampaignImpression, $hour, $impressions);
        $this->record($campaign->site, $campaign, EventType::CampaignClick, $hour, min($clicks, $impressions));
        $this->record($campaign->site, $campaign, EventType::Conversion, $hour, min($conversions, $clicks));
    }

    /**
     * Add an hourly rollup, plus matching raw processed events when the hour falls in the last 24 hours.
     */
    private function record(Site $site, ?Campaign $campaign, EventType $type, CarbonImmutable $hour, int $count): void
    {
        if ($count <= 0) {
            return;
        }

        $this->rollups[] = [
            'workspace_id' => $site->workspace_id,
            'site_id' => $site->id,
            'campaign_id' => $campaign?->id,
            'campaign_key' => $campaign->id ?? 0,
            'type' => $type->value,
            'bucket_start' => $hour,
            'count' => $count,
        ];

        if ($hour->lessThan($this->now->subDay())) {
            return;
        }

        $latestMinute = $hour->equalTo($this->now->startOfHour()) ? max(0, $this->now->minute - 1) : 59;

        for ($i = 0; $i < $count; $i++) {
            $occurredAt = $hour->addMinutes(mt_rand(0, $latestMinute))->addSeconds(mt_rand(0, 59));

            $this->rawEvents[] = [
                'workspace_id' => $site->workspace_id,
                'site_id' => $site->id,
                'campaign_id' => $campaign?->id,
                'event_id' => (string) Str::uuid(),
                'type' => $type->value,
                'visitor_id' => 'demo-visitor-'.mt_rand(1, 4000),
                'session_id' => 'demo-session-'.mt_rand(1, 9000),
                'url' => 'https://'.$site->domain.'/'.($type === EventType::Conversion ? 'checkout/thank-you' : 'products'),
                'referrer' => null,
                'properties' => $type === EventType::Conversion ? json_encode(['value' => mt_rand(25, 180)]) : null,
                'occurred_at' => $occurredAt,
                'received_at' => $occurredAt->addSecond(),
                'status' => EventStatus::Processed->value,
                'dispatched_at' => $occurredAt->addSecond(),
                'attempts' => 1,
                'processed_at' => $occurredAt->addSeconds(2),
            ];
        }
    }

    /**
     * Send recent events for the archived campaign through real processing, where they are rejected and marked failed.
     */
    private function stageFailedEvents(Campaign $archived): void
    {
        foreach (range(1, 6) as $minutesAgo) {
            $event = new IngestedEvent;
            $event->forceFill([
                'workspace_id' => $archived->workspace_id,
                'site_id' => $archived->site_id,
                'campaign_id' => $archived->id,
                'event_id' => (string) Str::uuid(),
                'type' => EventType::CampaignImpression,
                'visitor_id' => 'demo-visitor-'.mt_rand(1, 4000),
                'url' => 'https://'.$archived->site->domain.'/sale',
                'occurred_at' => $this->now->subMinutes($minutesAgo * 7),
                'received_at' => $this->now->subMinutes($minutesAgo * 7),
                'status' => EventStatus::Pending,
            ])->save();

            ProcessIngestedEvent::dispatchSync($event->id);
        }
    }

    /**
     * Add noise around an expected count.
     */
    private function vary(float $expected): int
    {
        return max(0, (int) round($expected * (0.8 + mt_rand(0, 400) / 1000)));
    }

    /**
     * The host (and port) of APP_URL, so the bundled /demo page passes the store site's origin check.
     */
    private function appHost(): string
    {
        $url = parse_url(config('app.url'));
        $host = Str::lower($url['host'] ?? 'localhost');

        return isset($url['port']) ? "{$host}:{$url['port']}" : $host;
    }
}
