<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CampaignPolicy
{
    /**
     * Determine whether the user can list campaigns in their current workspace.
     */
    public function viewAny(User $user): bool
    {
        return $user->current_workspace_id !== null;
    }

    /**
     * Determine whether the user can create campaigns in their current workspace.
     */
    public function create(User $user): bool
    {
        return $user->current_workspace_id !== null;
    }

    /**
     * Determine whether the user can view the campaign's analytics.
     */
    public function view(User $user, Campaign $campaign): Response
    {
        return $this->withinCurrentWorkspace($user, $campaign);
    }

    /**
     * Determine whether the user can update the campaign.
     */
    public function update(User $user, Campaign $campaign): Response
    {
        return $this->withinCurrentWorkspace($user, $campaign);
    }

    /**
     * Allow access only within the user's current workspace, hiding the existence of other tenants' records.
     */
    private function withinCurrentWorkspace(User $user, Campaign $campaign): Response
    {
        return $campaign->workspace_id === $user->current_workspace_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
