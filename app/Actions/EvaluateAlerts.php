<?php

namespace App\Actions;

use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\CampaignStatus;
use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\Alert;
use App\Models\Campaign;
use App\Models\EventRollup;
use App\Models\IngestedEvent;
use App\Notifications\AlertOpened;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Detects tracking problems across all workspaces and keeps alerts in step with them.
 *
 * Each run computes the set of conditions currently breaching. New breaches open an alert, ongoing ones refresh their numbers, and open alerts whose condition has cleared are resolved automatically.
 *
 * @phpstan-type Breach array{workspace_id: int, site_id: int, campaign_id: int|null, type: AlertType, context: array<string, mixed>}
 */
class EvaluateAlerts
{
    /**
     * @return array{opened: int, resolved: int, ongoing: int}
     */
    public function handle(CarbonImmutable $now): array
    {
        $breaches = collect([
            ...$this->volumeDrops($now),
            ...$this->processingFailures($now),
            ...$this->stalledProcessing($now),
        ])->keyBy(fn (array $breach) => $this->subjectKey($breach['site_id'], $breach['campaign_id'], $breach['type']));

        $openAlerts = Alert::query()
            ->where('status', AlertStatus::Open)
            ->get()
            ->keyBy(fn (Alert $alert) => $this->subjectKey($alert->site_id, $alert->campaign_id, $alert->type));

        $resolved = 0;

        foreach ($openAlerts->diffKeys($breaches) as $alert) {
            $alert->update(['status' => AlertStatus::Resolved, 'resolved_at' => $now, 'last_evaluated_at' => $now]);
            $resolved++;
        }

        $opened = 0;

        foreach ($breaches as $key => $breach) {
            $existing = $openAlerts->get($key);

            if ($existing !== null) {
                $existing->update(['context' => $breach['context'], 'last_evaluated_at' => $now]);

                continue;
            }

            if ($this->open($breach, $now)) {
                $opened++;
            }
        }

        return ['opened' => $opened, 'resolved' => $resolved, 'ongoing' => $breaches->count() - $opened];
    }

    /**
     * Compare the last complete hours with the same hours on previous days, for each site's page views and each active campaign's impressions.
     *
     * @return list<Breach>
     */
    private function volumeDrops(CarbonImmutable $now): array
    {
        $windowHours = config('tracking.alerts.window_hours');
        $baselineDays = config('tracking.alerts.baseline_days');
        $windowEnd = $now->utc()->startOfHour();
        $windowStart = $windowEnd->subHours($windowHours);

        $observed = $this->monitoredVolume(fn (Builder $query) => $query
            ->where('bucket_start', '>=', $windowStart)
            ->where('bucket_start', '<', $windowEnd));

        $baseline = $this->monitoredVolume(fn (Builder $query) => $query->where(function (Builder $windows) use ($windowStart, $windowEnd, $baselineDays) {
            for ($day = 1; $day <= $baselineDays; $day++) {
                $windows->orWhere(fn (Builder $window) => $window
                    ->where('bucket_start', '>=', $windowStart->subDays($day))
                    ->where('bucket_start', '<', $windowEnd->subDays($day)));
            }
        }));

        $activeCampaignIds = Campaign::query()
            ->where('status', CampaignStatus::Active)
            ->whereIn('id', $baseline->pluck('campaign_key')->filter()->unique())
            ->pluck('id')
            ->flip();

        $breaches = [];

        foreach ($baseline as $key => $row) {
            $campaignId = (int) $row->campaign_key ?: null;

            if ($campaignId !== null && ! $activeCampaignIds->has($campaignId)) {
                continue;
            }

            $expected = (int) $row->total / $baselineDays;
            $actual = (int) ($observed->get($key)->total ?? 0);

            if ($expected < config('tracking.alerts.min_baseline_events')
                || $actual >= $expected * (1 - config('tracking.alerts.drop_threshold'))) {
                continue;
            }

            $breaches[] = [
                'workspace_id' => (int) $row->workspace_id,
                'site_id' => (int) $row->site_id,
                'campaign_id' => $campaignId,
                'type' => AlertType::VolumeDrop,
                'context' => [
                    'metric' => $campaignId === null ? 'page_views' : 'impressions',
                    'observed' => $actual,
                    'expected' => round($expected, 1),
                    'drop' => round(1 - $actual / $expected, 3),
                    'window_hours' => $windowHours,
                    'baseline_days' => $baselineDays,
                ],
            ];
        }

        return $breaches;
    }

    /**
     * Sum the monitored traffic (site page views and campaign impressions) per subject within the given time filter.
     *
     * @param  callable(Builder<EventRollup>): mixed  $timeFilter
     * @return Collection<string, \stdClass>
     */
    private function monitoredVolume(callable $timeFilter): Collection
    {
        return EventRollup::query()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $site) => $site->where('campaign_key', 0)->where('type', EventType::PageView))
                ->orWhere(fn (Builder $campaign) => $campaign->where('campaign_key', '>', 0)->where('type', EventType::CampaignImpression)))
            ->tap($timeFilter)
            ->selectRaw('workspace_id, site_id, campaign_key, sum(count) as total')
            ->groupBy('workspace_id', 'site_id', 'campaign_key')
            ->toBase()
            ->get()
            ->keyBy(fn (\stdClass $row) => "{$row->site_id}:{$row->campaign_key}");
    }

    /**
     * Sites where many events recently failed processing.
     *
     * @return list<Breach>
     */
    private function processingFailures(CarbonImmutable $now): array
    {
        $windowMinutes = config('tracking.alerts.failure_window_minutes');

        return array_values(IngestedEvent::query()
            ->where('status', EventStatus::Failed)
            ->where('failed_at', '>=', $now->subMinutes($windowMinutes))
            ->selectRaw('workspace_id, site_id, count(*) as failed')
            ->groupBy('workspace_id', 'site_id')
            ->havingRaw('count(*) >= ?', [config('tracking.alerts.failure_threshold')])
            ->toBase()
            ->get()
            ->map(fn (\stdClass $row) => [
                'workspace_id' => (int) $row->workspace_id,
                'site_id' => (int) $row->site_id,
                'campaign_id' => null,
                'type' => AlertType::ProcessingFailures,
                'context' => ['failed' => (int) $row->failed, 'window_minutes' => $windowMinutes],
            ])
            ->all());
    }

    /**
     * Sites with events left pending well past the point where the stale-event sweep should have recovered them, which usually means no worker is running.
     *
     * @return list<Breach>
     */
    private function stalledProcessing(CarbonImmutable $now): array
    {
        $stalledAfterMinutes = config('tracking.alerts.stalled_after_minutes');

        return array_values(IngestedEvent::query()
            ->where('status', EventStatus::Pending)
            ->where('received_at', '<', $now->subMinutes($stalledAfterMinutes))
            ->selectRaw('workspace_id, site_id, count(*) as pending')
            ->groupBy('workspace_id', 'site_id')
            ->toBase()
            ->get()
            ->map(fn (\stdClass $row) => [
                'workspace_id' => (int) $row->workspace_id,
                'site_id' => (int) $row->site_id,
                'campaign_id' => null,
                'type' => AlertType::ProcessingStalled,
                'context' => ['pending' => (int) $row->pending, 'stalled_after_minutes' => $stalledAfterMinutes],
            ])
            ->all());
    }

    /**
     * Open an alert and notify the workspace. Returns false if a concurrent run opened the same alert first; the partial unique index guarantees only one survives.
     *
     * @param  Breach  $breach
     */
    private function open(array $breach, CarbonImmutable $now): bool
    {
        try {
            $alert = Alert::create([
                ...$breach,
                'campaign_key' => $breach['campaign_id'] ?? 0,
                'status' => AlertStatus::Open,
                'triggered_at' => $now,
                'last_evaluated_at' => $now,
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        Notification::send($alert->workspace->members, new AlertOpened($alert));

        return true;
    }

    private function subjectKey(int $siteId, ?int $campaignId, AlertType $type): string
    {
        return "{$siteId}:".($campaignId ?? 0).":{$type->value}";
    }
}
