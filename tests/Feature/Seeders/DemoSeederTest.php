<?php

use App\Enums\AlertType;
use App\Enums\EventStatus;
use App\Models\Alert;
use App\Models\EventRollup;
use App\Models\IngestedEvent;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    config(['app.url' => 'http://pulse.test']);
});

test('the demo workspace is seeded with history and staged problems for monitoring to find', function () {
    $this->seed(DemoSeeder::class);

    $user = User::where('email', DemoSeeder::EMAIL)->sole();
    expect($user->email_verified_at)->not->toBeNull();
    $store = Site::firstWhere('site_key', DemoSeeder::STORE_SITE_KEY);
    expect($store->workspace_id)->toBe($user->current_workspace_id);
    expect($store->allowsOrigin('http://pulse.test'))->toBeTrue();
    expect(EventRollup::where('workspace_id', $user->current_workspace_id)->sum('count'))->toBeGreaterThan(1000);
    expect(IngestedEvent::where('status', EventStatus::Processed)->where('received_at', '<', now()->subDay()->subHour())->count())->toBe(0);
    expect(IngestedEvent::where('status', EventStatus::Failed)->count())->toBe(6);
    expect(Alert::pluck('type')->map->value->sort()->values()->all())->toBe([
        AlertType::ProcessingFailures->value,
        AlertType::VolumeDrop->value,
    ]);
    expect(Alert::firstWhere('type', AlertType::VolumeDrop)->campaign->name)->toBe('Free shipping banner');
});

test('running the seeder again replaces the previous demo workspace', function () {
    $this->seed(DemoSeeder::class);

    $this->seed(DemoSeeder::class);

    expect(User::where('email', DemoSeeder::EMAIL)->count())->toBe(1);
    expect(Site::where('site_key', DemoSeeder::STORE_SITE_KEY)->count())->toBe(1);
    expect(Alert::count())->toBe(2);
});
