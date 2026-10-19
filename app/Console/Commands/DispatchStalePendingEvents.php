<?php

namespace App\Console\Commands;

use App\Actions\DispatchEventProcessing;
use App\Enums\EventStatus;
use App\Models\IngestedEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

#[Signature('events:dispatch-stale {--minutes=10 : Re-queue pending events not handed to the queue within this many minutes}')]
#[Description('Re-queue pending events whose processing job was never queued or was lost')]
class DispatchStalePendingEvents extends Command
{
    /**
     * Execute the console command.
     *
     * Events are stored before their job is queued, so the database is the source of truth: an event can be left pending if queueing failed or a job was lost. This sweep finds those events and queues them again.
     */
    public function handle(DispatchEventProcessing $dispatchEventProcessing): int
    {
        $staleBefore = Date::now()->subMinutes((int) $this->option('minutes'));
        $dispatched = 0;

        IngestedEvent::query()
            ->where('status', EventStatus::Pending)
            ->where(fn ($query) => $query
                ->whereNull('dispatched_at')
                ->orWhere('dispatched_at', '<', $staleBefore))
            ->select('id')
            ->chunkById(500, function (Collection $events) use ($dispatchEventProcessing, &$dispatched) {
                $dispatchEventProcessing->handle($events->pluck('id'));
                $dispatched += $events->count();
            });

        $this->components->info("Re-queued {$dispatched} stale pending events.");

        return self::SUCCESS;
    }
}
