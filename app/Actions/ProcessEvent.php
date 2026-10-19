<?php

namespace App\Actions;

use App\Enums\CampaignStatus;
use App\Enums\EventStatus;
use App\Exceptions\EventRejected;
use App\Models\EventRollup;
use App\Models\IngestedEvent;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class ProcessEvent
{
    /**
     * Process an ingested event into the hourly rollups exactly once.
     *
     * The event is claimed by a conditional status update in the same transaction as the rollup increment. A concurrent or repeated run finds nothing left to claim and does nothing, and any failure rolls both back so the event stays retryable.
     *
     * @return bool Whether this call processed the event.
     *
     * @throws EventRejected
     */
    public function handle(int $ingestedEventId): bool
    {
        return DB::transaction(function () use ($ingestedEventId): bool {
            $claimed = IngestedEvent::query()
                ->whereKey($ingestedEventId)
                ->whereIn('status', [EventStatus::Pending, EventStatus::Failed])
                ->update([
                    'status' => EventStatus::Processed,
                    'processed_at' => Date::now(),
                    'attempts' => DB::raw('attempts + 1'),
                    'last_error' => null,
                    'failed_at' => null,
                ]);

            if ($claimed === 0) {
                return false;
            }

            $event = IngestedEvent::with('campaign:id,status')->findOrFail($ingestedEventId);

            if ($event->campaign?->status === CampaignStatus::Archived) {
                throw new EventRejected(__('The campaign is archived. Unarchive it and retry to count this event.'));
            }

            $this->incrementRollup($event);

            return true;
        });
    }

    /**
     * Add the event to its hourly bucket with an atomic insert-or-increment.
     */
    private function incrementRollup(IngestedEvent $event): void
    {
        EventRollup::query()->upsert(
            [[
                'workspace_id' => $event->workspace_id,
                'site_id' => $event->site_id,
                'campaign_id' => $event->campaign_id,
                'campaign_key' => $event->campaign_id ?? 0,
                'type' => $event->type->value,
                'bucket_start' => $event->occurred_at->utc()->startOfHour(),
                'count' => 1,
            ]],
            uniqueBy: ['site_id', 'campaign_key', 'type', 'bucket_start'],
            update: ['count' => DB::raw('event_rollups.count + 1')],
        );
    }
}
