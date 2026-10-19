<?php

use App\Models\Campaign;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('campaigns in the current workspace can be updated', function () {
    $user = User::factory()->withWorkspace()->create();
    $campaign = Campaign::factory()->for(Site::factory()->for($user->currentWorkspace))->create();

    expect($user->can('update', $campaign))->toBeTrue();
});

test('campaigns in another workspace are hidden as not found', function () {
    $user = User::factory()->withWorkspace()->create();
    $campaign = Campaign::factory()->create();

    $response = Gate::forUser($user)->inspect('update', $campaign);

    expect($response->denied())->toBeTrue();
    expect($response->status())->toBe(404);
});

test('campaigns cannot be listed or created without a current workspace', function (string $ability) {
    $user = User::factory()->create();

    expect($user->can($ability, Campaign::class))->toBeFalse();
})->with(['viewAny', 'create']);
