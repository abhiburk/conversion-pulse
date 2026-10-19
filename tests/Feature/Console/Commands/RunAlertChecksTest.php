<?php

use App\Enums\EventStatus;
use App\Models\IngestedEvent;
use Illuminate\Support\Facades\Notification;

test('the command reports what it opened and resolved', function () {
    Notification::fake();
    IngestedEvent::factory()->create(['status' => EventStatus::Pending, 'received_at' => now()->subHour()]);

    $this->artisan('alerts:evaluate')
        ->expectsOutputToContain('Alerts opened: 1, ongoing: 0, resolved: 0.')
        ->assertSuccessful();
});
