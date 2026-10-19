<?php

use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('sites in the current workspace can be updated and deleted', function (string $ability) {
    $user = User::factory()->withWorkspace()->create();
    $site = Site::factory()->for($user->currentWorkspace)->create();

    expect($user->can($ability, $site))->toBeTrue();
})->with(['update', 'delete']);

test('sites in another workspace are hidden as not found', function (string $ability) {
    $user = User::factory()->withWorkspace()->create();
    $site = Site::factory()->create();

    $response = Gate::forUser($user)->inspect($ability, $site);

    expect($response->denied())->toBeTrue();
    expect($response->status())->toBe(404);
})->with(['update', 'delete']);

test('sites cannot be listed or created without a current workspace', function (string $ability) {
    $user = User::factory()->create();

    expect($user->can($ability, Site::class))->toBeFalse();
})->with(['viewAny', 'create']);
