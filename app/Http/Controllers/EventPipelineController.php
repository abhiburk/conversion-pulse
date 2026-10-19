<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\IngestedEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EventPipelineController extends Controller
{
    /**
     * Show processing health for the current workspace and the events that failed processing.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', IngestedEvent::class);

        $events = $request->user()->currentWorkspace->ingestedEvents();
        $oldestPendingAt = (clone $events)->where('status', EventStatus::Pending)->min('received_at');

        return Inertia::render('pipeline/Index', [
            'stats' => [
                'pending' => (clone $events)->where('status', EventStatus::Pending)->count(),
                'oldestPendingAt' => $oldestPendingAt ? Date::parse($oldestPendingAt)->toIso8601String() : null,
                'processedLastDay' => (clone $events)
                    ->where('status', EventStatus::Processed)
                    ->where('processed_at', '>=', Date::now()->subDay())
                    ->count(),
                'failed' => (clone $events)->where('status', EventStatus::Failed)->count(),
            ],
            'failedEvents' => (clone $events)
                ->where('status', EventStatus::Failed)
                ->with(['site:id,name', 'campaign:id,name'])
                ->orderByDesc('failed_at')
                ->orderByDesc('id')
                ->paginate(20)
                ->through(fn (IngestedEvent $event) => [
                    'id' => $event->id,
                    'event_id' => $event->event_id,
                    'type' => $event->type,
                    'site' => $event->site->name,
                    'campaign' => $event->campaign?->name,
                    'url' => $event->url,
                    'properties' => $event->properties,
                    'attempts' => $event->attempts,
                    'last_error' => $event->last_error,
                    'occurred_at' => $event->occurred_at->toIso8601String(),
                    'failed_at' => $event->failed_at?->toIso8601String(),
                ]),
        ]);
    }
}
