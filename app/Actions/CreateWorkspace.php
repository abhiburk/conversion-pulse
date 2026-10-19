<?php

namespace App\Actions;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreateWorkspace
{
    /**
     * Create a workspace owned by the given user and make it their current workspace.
     */
    public function handle(User $owner, string $name): Workspace
    {
        return DB::transaction(function () use ($owner, $name): Workspace {
            $workspace = Workspace::create(['name' => $name]);

            $workspace->members()->attach($owner, ['role' => WorkspaceRole::Owner->value]);

            $owner->forceFill(['current_workspace_id' => $workspace->id])->save();

            return $workspace;
        });
    }
}
