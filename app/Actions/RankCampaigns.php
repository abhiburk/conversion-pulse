<?php

namespace App\Actions;

use App\Enums\CampaignStatus;
use App\Enums\EventType;
use App\Models\Campaign;
use App\Models\EventRollup;
use App\Models\Workspace;
use App\Support\DateRange;
use Illuminate\Database\Eloquent\Builder;

class RankCampaigns
{
    /**
     * Rank the workspace's campaigns by impressions in the range.
     *
     * Active campaigns are listed even when they received nothing, because a live campaign with zero impressions usually means its tracking is broken.
     *
     * @return list<array{id: int, name: string, site: string, status: CampaignStatus, impressions: int, clicks: int, conversions: int, click_through_rate: float|null, conversion_rate: float|null}>
     */
    public function handle(Workspace $workspace, DateRange $range, int $limit = 20): array
    {
        $totals = EventRollup::query()
            ->where('workspace_id', $workspace->id)
            ->whereNotNull('campaign_id')
            ->whereIn('type', [EventType::CampaignImpression, EventType::CampaignClick, EventType::Conversion])
            ->where('bucket_start', '>=', $range->from)
            ->where('bucket_start', '<', $range->to)
            ->selectRaw('campaign_id, type, sum(count) as total')
            ->groupBy('campaign_id', 'type')
            ->toBase()
            ->get()
            ->groupBy('campaign_id');

        return array_values($workspace->campaigns()
            ->with('site:id,name')
            ->where(fn (Builder $query) => $query
                ->where('status', CampaignStatus::Active)
                ->orWhereIn('id', $totals->keys()))
            ->get(['id', 'site_id', 'name', 'status'])
            ->map(function (Campaign $campaign) use ($totals) {
                $counts = ($totals->get($campaign->id) ?? collect())->pluck('total', 'type');
                $impressions = (int) $counts->get(EventType::CampaignImpression->value, 0);
                $clicks = (int) $counts->get(EventType::CampaignClick->value, 0);
                $conversions = (int) $counts->get(EventType::Conversion->value, 0);

                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'site' => $campaign->site->name,
                    'status' => $campaign->status,
                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'conversions' => $conversions,
                    'click_through_rate' => SummarizeEvents::rate($clicks, $impressions),
                    'conversion_rate' => SummarizeEvents::rate($conversions, $impressions),
                ];
            })
            ->sortBy([['impressions', 'desc'], ['name', 'asc']])
            ->take($limit)
            ->all());
    }
}
