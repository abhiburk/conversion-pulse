<?php

use App\Models\Alert;
use App\Models\Site;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('alerts.index'))->assertRedirect(route('login'));
});

test('open and resolved alerts are listed for the current workspace only', function () {
    $user = User::factory()->withWorkspace()->create();
    $site = Site::factory()->for($user->currentWorkspace)->create();
    $open = Alert::factory()->for($site)->create();
    $resolved = Alert::factory()->for($site)->resolved()->create(['type' => 'processing_stalled', 'context' => ['pending' => 4, 'stalled_after_minutes' => 15]]);
    Alert::factory()->create();

    $response = $this->actingAs($user)->get(route('alerts.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('alerts/Index')
        ->has('openAlerts', 1)
        ->where('openAlerts.0.id', $open->id)
        ->where('openAlerts.0.summary', '8 events failed processing in the last 60 minutes.')
        ->has('resolvedAlerts.data', 1)
        ->where('resolvedAlerts.data.0.id', $resolved->id)
    );
});
