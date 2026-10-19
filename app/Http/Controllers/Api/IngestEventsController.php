<?php

namespace App\Http\Controllers\Api;

use App\Actions\IngestEvents;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IngestEventsRequest;
use App\Models\Site;
use Illuminate\Http\JsonResponse;

class IngestEventsController extends Controller
{
    /**
     * Accept a batch of tracking events from the SDK.
     */
    public function __invoke(IngestEventsRequest $request, IngestEvents $ingestEvents): JsonResponse
    {
        $site = Site::firstWhere('site_key', $request->validated('site_key'));

        abort_if($site === null, 401, __('Unknown site key.'));
        abort_unless($site->allowsOrigin($request->header('Origin')), 403, __('This origin is not allowed to send events for the site.'));

        return response()->json($ingestEvents->handle($site, $request->validated('events')), 202);
    }
}
