<?php

use App\Enums\AlertStatus;
use App\Models\Alert;
use App\Models\Site;
use App\Models\User;

test('acknowledging records who is handling the alert and keeps it open', function () {
    $user = User::factory()->withWorkspace()->create();
    $alert = Alert::factory()->for(Site::factory()->for($user->currentWorkspace))->create();

    $response = $this->actingAs($user)->post(route('alerts.acknowledge', $alert));

    $response->assertRedirect(route('alerts.index'));
    $alert->refresh();
    expect($alert->acknowledged_by)->toBe($user->id);
    expect($alert->acknowledged_at)->not->toBeNull();
    expect($alert->status)->toBe(AlertStatus::Open);
});

test('an alert acknowledged by someone else keeps its original acknowledgement', function () {
    $user = User::factory()->withWorkspace()->create();
    $colleague = User::factory()->create();
    $alert = Alert::factory()->for(Site::factory()->for($user->currentWorkspace))->create([
        'acknowledged_at' => now()->subHour(),
        'acknowledged_by' => $colleague->id,
    ]);

    $this->actingAs($user)->post(route('alerts.acknowledge', $alert));

    expect($alert->fresh()->acknowledged_by)->toBe($colleague->id);
});

test('an alert from another workspace returns 404 and is unchanged', function () {
    $user = User::factory()->withWorkspace()->create();
    $otherAlert = Alert::factory()->create();

    $response = $this->actingAs($user)->post(route('alerts.acknowledge', $otherAlert));

    $response->assertNotFound();
    expect($otherAlert->fresh()->acknowledged_at)->toBeNull();
});
