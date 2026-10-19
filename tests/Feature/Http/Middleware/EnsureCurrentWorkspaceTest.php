<?php

use App\Models\User;
use App\Models\Workspace;

test('users without a current workspace get 403', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('sites.index'))->assertForbidden();
});

test('users whose current workspace they do not belong to get 403', function () {
    $user = User::factory()->create();
    $user->forceFill(['current_workspace_id' => Workspace::factory()->create()->id])->save();

    $this->actingAs($user)->get(route('sites.index'))->assertForbidden();
});

test('members of their current workspace are let through', function () {
    $user = User::factory()->withWorkspace()->create();

    $this->actingAs($user)->get(route('sites.index'))->assertOk();
});
