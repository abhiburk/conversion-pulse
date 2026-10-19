<?php

use App\Models\IngestedEvent;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('events in the current workspace can be retried', function () {
    $user = User::factory()->withWorkspace()->create();
    $event = IngestedEvent::factory()->for(Site::factory()->for($user->currentWorkspace))->create();

    expect($user->can('retry', $event))->toBeTrue();
});

test('events in another workspace are hidden as not found', function () {
    $user = User::factory()->withWorkspace()->create();
    $event = IngestedEvent::factory()->create();

    $response = Gate::forUser($user)->inspect('retry', $event);

    expect($response->denied())->toBeTrue();
    expect($response->status())->toBe(404);
});

test('events cannot be inspected without a current workspace', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', IngestedEvent::class))->toBeFalse();
});
