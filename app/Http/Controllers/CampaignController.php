<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    /**
     * List the campaigns in the current workspace.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Campaign::class);

        return Inertia::render('campaigns/Index', [
            'campaigns' => $request->user()->currentWorkspace->campaigns()
                ->with('site:id,name,domain')
                ->latest()
                ->orderByDesc('id')
                ->get(['id', 'site_id', 'public_id', 'name', 'status', 'created_at']),
        ]);
    }

    /**
     * Show the form for creating a campaign.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Campaign::class);

        return Inertia::render('campaigns/Create', [
            'sites' => $request->user()->currentWorkspace->sites()
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name', 'domain']),
            'statuses' => CampaignStatus::cases(),
        ]);
    }

    /**
     * Create a campaign in the current workspace.
     */
    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $campaign = $request->user()->currentWorkspace->campaigns()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign created.')]);

        return to_route('campaigns.edit', $campaign);
    }

    /**
     * Show the form for editing a campaign.
     */
    public function edit(Campaign $campaign): Response
    {
        Gate::authorize('update', $campaign);

        return Inertia::render('campaigns/Edit', [
            'campaign' => [
                ...$campaign->only(['id', 'public_id', 'name', 'status']),
                'site' => $campaign->site->only(['id', 'name', 'domain']),
            ],
            'statuses' => CampaignStatus::cases(),
        ]);
    }

    /**
     * Update a campaign.
     */
    public function update(UpdateCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $campaign->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign updated.')]);

        return to_route('campaigns.edit', $campaign);
    }
}
