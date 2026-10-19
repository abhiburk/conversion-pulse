<?php

namespace App\Http\Controllers;

use App\Actions\RankCampaigns;
use App\Actions\SummarizeEvents;
use App\Http\Requests\AnalyticsRangeRequest;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show tracking analytics for the current workspace.
     */
    public function index(AnalyticsRangeRequest $request, SummarizeEvents $summarizeEvents, RankCampaigns $rankCampaigns): Response
    {
        $workspace = $request->user()->currentWorkspace;
        $range = $request->dateRange();

        return Inertia::render('Dashboard', [
            'filters' => $range->toArray(),
            'analytics' => $summarizeEvents->handle($workspace, $range),
            'campaigns' => $rankCampaigns->handle($workspace, $range),
            'hasSites' => $workspace->sites()->exists(),
        ]);
    }
}
