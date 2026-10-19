<?php

namespace App\Actions;

use App\Jobs\ProcessIngestedEvent;
use App\Models\IngestedEvent;
use Illuminate\Support\Facades\Date;

class DispatchEventProcessing
{
    /**
     * Queue processing for the given events and record when they were handed to the queue.
     *
     * Dispatching an event more than once is harmless because processing claims each event exactly once.
     *
     * @param  iterable<int>  $ingestedEventIds
     */
    public function handle(iterable $ingestedEventIds): void
    {
        foreach (collect($ingestedEventIds)->chunk(500) as $chunk) {
            IngestedEvent::query()->whereKey($chunk->all())->update(['dispatched_at' => Date::now()]);

            $chunk->each(fn (int $id) => ProcessIngestedEvent::dispatch($id));
        }
    }
}
