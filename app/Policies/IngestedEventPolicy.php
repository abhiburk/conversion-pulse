<?php

namespace App\Policies;

use App\Models\IngestedEvent;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class IngestedEventPolicy
{
    /**
     * Determine whether the user can inspect events in their current workspace.
     */
    public function viewAny(User $user): bool
    {
        return $user->current_workspace_id !== null;
    }

    /**
     * Determine whether the user can retry the event, hiding the existence of other tenants' events.
     */
    public function retry(User $user, IngestedEvent $ingestedEvent): Response
    {
        return $ingestedEvent->workspace_id === $user->current_workspace_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
