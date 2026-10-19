<?php

namespace App\Actions;

use App\Enums\EventType;
use App\Models\Campaign;
use App\Models\EventRollup;
use App\Models\Workspace;
use App\Support\DateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use stdClass;

/**
 * Summarizes processed events from the hourly rollups for a workspace or a single campaign.
 *
 * @phpstan-type Counts array{page_views: int, impressions: int, clicks: int, conversions: int, attributed_conversions: int}
 * @phpstan-type Metrics array{page_views: int, impressions: int, clicks: int, conversions: int, attributed_conversions: int, click_through_rate: float|null, conversion_rate: float|null}
 */
class SummarizeEvents
{
    /**
     * @return array{totals: Metrics, previous: Metrics, series: list<array{bucket: string, page_views: int, impressions: int, clicks: int, conversions: int}>}
     */
    public function handle(Workspace $workspace, DateRange $range, ?Campaign $campaign = null): array
    {
        $series = [];

        foreach ($range->buckets() as $bucket) {
            $series[$bucket->toIso8601String()] = $this->emptyCounts();
        }

        foreach ($this->rows($workspace, $range, $campaign) as $row) {
            $bucket = $range->bucketFor(Date::parse($row->bucket_start, 'UTC'))->toIso8601String();

            if (isset($series[$bucket])) {
                $series[$bucket] = $this->add($series[$bucket], $row);
            }
        }

        $previous = $this->rows($workspace, $range->previous(), $campaign)
            ->reduce(fn (array $counts, stdClass $row) => $this->add($counts, $row), $this->emptyCounts());

        return [
            'totals' => $this->metrics($this->sum($series)),
            'previous' => $this->metrics($previous),
            'series' => array_values(collect($series)
                ->map(fn (array $counts, string $bucket) => [
                    'bucket' => $bucket,
                    'page_views' => $counts['page_views'],
                    'impressions' => $counts['impressions'],
                    'clicks' => $counts['clicks'],
                    'conversions' => $counts['conversions'],
                ])
                ->all()),
        ];
    }

    /**
     * Fetch hourly counts per event type, split by whether the event was attributed to a campaign.
     *
     * Each row has bucket_start, type, attributed (0 or 1) and total.
     *
     * @return Collection<int, stdClass>
     */
    private function rows(Workspace $workspace, DateRange $range, ?Campaign $campaign): Collection
    {
        $attributed = 'case when campaign_key = 0 then 0 else 1 end';

        return EventRollup::query()
            ->where('workspace_id', $workspace->id)
            ->when($campaign, fn (Builder $query) => $query->where('campaign_id', $campaign->id))
            ->where('bucket_start', '>=', $range->from)
            ->where('bucket_start', '<', $range->to)
            ->selectRaw("bucket_start, type, {$attributed} as attributed, sum(count) as total")
            ->groupByRaw("bucket_start, type, {$attributed}")
            ->toBase()
            ->get();
    }

    /**
     * @param  Counts  $counts
     * @return Counts
     */
    private function add(array $counts, stdClass $row): array
    {
        $total = (int) $row->total;

        match (EventType::from($row->type)) {
            EventType::PageView => $counts['page_views'] += $total,
            EventType::CampaignImpression => $counts['impressions'] += $total,
            EventType::CampaignClick => $counts['clicks'] += $total,
            EventType::Conversion => $counts['conversions'] += $total,
        };

        if ($row->type === EventType::Conversion->value && (int) $row->attributed === 1) {
            $counts['attributed_conversions'] += $total;
        }

        return $counts;
    }

    /**
     * @param  array<string, Counts>  $series
     * @return Counts
     */
    private function sum(array $series): array
    {
        return array_reduce($series, fn (array $carry, array $counts) => [
            'page_views' => $carry['page_views'] + $counts['page_views'],
            'impressions' => $carry['impressions'] + $counts['impressions'],
            'clicks' => $carry['clicks'] + $counts['clicks'],
            'conversions' => $carry['conversions'] + $counts['conversions'],
            'attributed_conversions' => $carry['attributed_conversions'] + $counts['attributed_conversions'],
        ], $this->emptyCounts());
    }

    /**
     * Derive rates from counts. Rates are relative to impressions and only use conversions attributed to a campaign, so site-wide conversions do not inflate them.
     *
     * @param  Counts  $counts
     * @return Metrics
     */
    private function metrics(array $counts): array
    {
        return [
            ...$counts,
            'click_through_rate' => self::rate($counts['clicks'], $counts['impressions']),
            'conversion_rate' => self::rate($counts['attributed_conversions'], $counts['impressions']),
        ];
    }

    /**
     * @return Counts
     */
    private function emptyCounts(): array
    {
        return ['page_views' => 0, 'impressions' => 0, 'clicks' => 0, 'conversions' => 0, 'attributed_conversions' => 0];
    }

    /**
     * Divide safely, returning null when there is nothing to divide by.
     */
    public static function rate(int $numerator, int $denominator): ?float
    {
        return $denominator > 0 ? round($numerator / $denominator, 4) : null;
    }
}
