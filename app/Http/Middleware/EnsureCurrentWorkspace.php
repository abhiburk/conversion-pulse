<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentWorkspace
{
    /**
     * Reject the request unless the user belongs to their current workspace.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user->current_workspace_id !== null
                && $user->workspaces()->whereKey($user->current_workspace_id)->exists(),
            403,
            __('You do not have access to a workspace.'),
        );

        return $next($request);
    }
}
