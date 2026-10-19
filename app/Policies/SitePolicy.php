<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SitePolicy
{
    /**
     * Determine whether the user can list sites in their current workspace.
     */
    public function viewAny(User $user): bool
    {
        return $user->current_workspace_id !== null;
    }

    /**
     * Determine whether the user can create sites in their current workspace.
     */
    public function create(User $user): bool
    {
        return $user->current_workspace_id !== null;
    }

    /**
     * Determine whether the user can update the site.
     */
    public function update(User $user, Site $site): Response
    {
        return $this->withinCurrentWorkspace($user, $site);
    }

    /**
     * Determine whether the user can delete the site.
     */
    public function delete(User $user, Site $site): Response
    {
        return $this->withinCurrentWorkspace($user, $site);
    }

    /**
     * Allow access only within the user's current workspace, hiding the existence of other tenants' records.
     */
    private function withinCurrentWorkspace(User $user, Site $site): Response
    {
        return $site->workspace_id === $user->current_workspace_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
