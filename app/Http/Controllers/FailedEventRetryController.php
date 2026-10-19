<?php

namespace App\Http\Controllers;

use App\Actions\RetryFailedEvents;
use App\Models\IngestedEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class FailedEventRetryController extends Controller
{
    /**
     * Retry one failed event.
     */
    public function store(Request $request, IngestedEvent $ingestedEvent, RetryFailedEvents $retryFailedEvents): RedirectResponse
    {
        Gate::authorize('retry', $ingestedEvent);

        $retried = $retryFailedEvents->handle($request->user()->currentWorkspace, $ingestedEvent);

        Inertia::flash('toast', $retried > 0
            ? ['type' => 'success', 'message' => __('Event queued for retry.')]
            : ['type' => 'info', 'message' => __('This event is no longer failed.')]);

        return to_route('pipeline.index');
    }

    /**
     * Retry every failed event in the current workspace.
     */
    public function storeAll(Request $request, RetryFailedEvents $retryFailedEvents): RedirectResponse
    {
        Gate::authorize('viewAny', IngestedEvent::class);

        $retried = $retryFailedEvents->handle($request->user()->currentWorkspace);

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{0} No failed events to retry.|{1} :count event queued for retry.|[2,*] :count events queued for retry.', $retried)]);

        return to_route('pipeline.index');
    }
}
