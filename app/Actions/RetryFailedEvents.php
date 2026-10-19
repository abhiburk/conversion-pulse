<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Models\IngestedEvent;
use App\Models\Workspace;
use Illuminate\Support\Collection;

class RetryFailedEvents
{
    public function __construct(private DispatchEventProcessing $dispatchEventProcessing) {}

    /**
     * Move failed events in the workspace back to pending and queue them again. Pass an event to retry only that one.
     *
     * Only events still marked failed are reset, so a second click or a concurrent retry cannot double-process anything.
     *
     * @return int The number of events queued for retry.
     */
    public function handle(Workspace $workspace, ?IngestedEvent $event = null): int
    {
        $retried = 0;

        $workspace->ingestedEvents()
            ->where('status', EventStatus::Failed)
            ->when($event, fn ($query) => $query->whereKey($event->id))
            ->select('id')
            ->chunkById(500, function (Collection $events) use (&$retried) {
                $ids = $events->pluck('id');

                $reset = IngestedEvent::query()
                    ->whereKey($ids->all())
                    ->where('status', EventStatus::Failed)
                    ->update(['status' => EventStatus::Pending, 'failed_at' => null]);

                if ($reset > 0) {
                    $this->dispatchEventProcessing->handle($ids);
                    $retried += $reset;
                }
            });

        return $retried;
    }
}
