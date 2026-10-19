<?php

use App\Enums\EventStatus;
use App\Models\IngestedEvent;
use App\Models\Site;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('pipeline.index'))->assertRedirect(route('login'));
});

test('stats and failed events only cover the current workspace', function () {
    $this->travelTo('2026-10-19 12:00:00');
    $user = User::factory()->withWorkspace()->create();
    $site = Site::factory()->for($user->currentWorkspace)->create();
    IngestedEvent::factory()->for($site)->create(['received_at' => '2026-10-19 11:50:00']);
    IngestedEvent::factory()->for($site)->create(['received_at' => '2026-10-19 11:30:00']);
    IngestedEvent::factory()->for($site)->create(['status' => EventStatus::Processed, 'processed_at' => '2026-10-19 09:00:00']);
    IngestedEvent::factory()->for($site)->create(['status' => EventStatus::Processed, 'processed_at' => '2026-10-17 09:00:00']);
    $failed = IngestedEvent::factory()->for($site)->create([
        'status' => EventStatus::Failed,
        'last_error' => 'The campaign is archived.',
        'failed_at' => now(),
    ]);
    IngestedEvent::factory()->count(3)->create(['status' => EventStatus::Failed]);

    $response = $this->actingAs($user)->get(route('pipeline.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('pipeline/Index')
        ->where('stats.pending', 2)
        ->where('stats.oldestPendingAt', '2026-10-19T11:30:00+00:00')
        ->where('stats.processedLastDay', 1)
        ->where('stats.failed', 1)
        ->has('failedEvents.data', 1)
        ->where('failedEvents.data.0.id', $failed->id)
        ->where('failedEvents.data.0.last_error', 'The campaign is archived.')
    );
});
