<?php

use App\Models\Alert;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('alerts in the current workspace can be acknowledged', function () {
    $user = User::factory()->withWorkspace()->create();
    $alert = Alert::factory()->for(Site::factory()->for($user->currentWorkspace))->create();

    expect($user->can('acknowledge', $alert))->toBeTrue();
});

test('alerts in another workspace are hidden as not found', function () {
    $user = User::factory()->withWorkspace()->create();
    $alert = Alert::factory()->create();

    $response = Gate::forUser($user)->inspect('acknowledge', $alert);

    expect($response->denied())->toBeTrue();
    expect($response->status())->toBe(404);
});

test('alerts cannot be listed without a current workspace', function () {
    expect(User::factory()->create()->can('viewAny', Alert::class))->toBeFalse();
});
