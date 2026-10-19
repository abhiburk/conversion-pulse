<?php

use App\Models\Campaign;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('campaigns in the current workspace can be viewed and updated', function (string $ability) {
    $user = User::factory()->withWorkspace()->create();
    $campaign = Campaign::factory()->for(Site::factory()->for($user->currentWorkspace))->create();

    expect($user->can($ability, $campaign))->toBeTrue();
})->with(['view', 'update']);

test('campaigns in another workspace are hidden as not found', function (string $ability) {
    $user = User::factory()->withWorkspace()->create();
    $campaign = Campaign::factory()->create();

    $response = Gate::forUser($user)->inspect($ability, $campaign);

    expect($response->denied())->toBeTrue();
    expect($response->status())->toBe(404);
})->with(['view', 'update']);

test('campaigns cannot be listed or created without a current workspace', function (string $ability) {
    $user = User::factory()->create();

    expect($user->can($ability, Campaign::class))->toBeFalse();
})->with(['viewAny', 'create']);
