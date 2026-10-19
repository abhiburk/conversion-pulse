<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSiteRequest;
use App\Http\Requests\UpdateSiteRequest;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    /**
     * List the sites in the current workspace.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Site::class);

        return Inertia::render('sites/Index', [
            'sites' => $request->user()->currentWorkspace->sites()
                ->withCount('campaigns')
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name', 'domain', 'site_key']),
        ]);
    }

    /**
     * Show the form for adding a site.
     */
    public function create(): Response
    {
        Gate::authorize('create', Site::class);

        return Inertia::render('sites/Create');
    }

    /**
     * Add a site to the current workspace.
     */
    public function store(StoreSiteRequest $request): RedirectResponse
    {
        $site = $request->user()->currentWorkspace->sites()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Site added.')]);

        return to_route('sites.edit', $site);
    }

    /**
     * Show the form for editing a site.
     */
    public function edit(Site $site): Response
    {
        Gate::authorize('update', $site);

        return Inertia::render('sites/Edit', [
            'site' => $site->only(['id', 'name', 'domain', 'site_key']),
        ]);
    }

    /**
     * Update a site.
     */
    public function update(UpdateSiteRequest $request, Site $site): RedirectResponse
    {
        $site->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Site updated.')]);

        return to_route('sites.edit', $site);
    }

    /**
     * Delete a site along with its campaigns.
     */
    public function destroy(Site $site): RedirectResponse
    {
        Gate::authorize('delete', $site);

        $site->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Site deleted.')]);

        return to_route('sites.index');
    }
}
