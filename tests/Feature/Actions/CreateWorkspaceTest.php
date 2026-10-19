<?php

use App\Actions\CreateWorkspace;
use App\Enums\WorkspaceRole;
use App\Models\User;

test('the owner becomes a member with the owner role', function () {
    $user = User::factory()->create();

    $workspace = app(CreateWorkspace::class)->handle($user, 'Acme Marketing');

    expect($workspace->name)->toBe('Acme Marketing');
    $this->assertDatabaseHas('user_workspace', [
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::Owner->value,
    ]);
});

test('the new workspace becomes the owner\'s current workspace', function () {
    $user = User::factory()->withWorkspace()->create();
    $previousWorkspaceId = $user->current_workspace_id;

    $workspace = app(CreateWorkspace::class)->handle($user, 'Second Workspace');

    expect($user->fresh()->current_workspace_id)
        ->toBe($workspace->id)
        ->not->toBe($previousWorkspaceId);
});
