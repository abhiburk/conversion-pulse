<?php

namespace App\Policies;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AlertPolicy
{
    /**
     * Determine whether the user can list alerts in their current workspace.
     */
    public function viewAny(User $user): bool
    {
        return $user->current_workspace_id !== null;
    }

    /**
     * Determine whether the user can acknowledge the alert, hiding the existence of other tenants' alerts.
     */
    public function acknowledge(User $user, Alert $alert): Response
    {
        return $alert->workspace_id === $user->current_workspace_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
