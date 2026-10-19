<?php

use App\Models\Alert;
use App\Models\Site;
use App\Models\User;
use App\Notifications\AlertOpened;

test('the email names the problem and links to the alerts page', function () {
    $alert = Alert::factory()->for(Site::factory()->state(['name' => 'Storefront', 'domain' => 'shop.example.com']))->create([
        'context' => ['failed' => 8, 'window_minutes' => 60],
    ]);

    $mail = (new AlertOpened($alert))->toMail(User::factory()->make());

    expect($mail->subject)->toBe('Processing failures: Storefront');
    expect($mail->introLines)->toBe([
        'Conversion Pulse detected a problem with Storefront on shop.example.com.',
        '8 events failed processing in the last 60 minutes.',
    ]);
    expect($mail->actionUrl)->toBe(route('alerts.index'));
});
