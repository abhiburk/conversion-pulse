<?php

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\Campaign;
use App\Models\IngestedEvent;
use App\Models\Site;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Build a valid tracking event payload as the SDK would send it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function trackingEvent(array $overrides = []): array
{
    return [
        'event_id' => (string) Str::uuid(),
        'type' => 'page_view',
        'occurred_at' => now()->toIso8601String(),
        'visitor_id' => 'visitor-1',
        'session_id' => 'session-1',
        'url' => 'https://example.com/pricing',
        ...$overrides,
    ];
}

/**
 * Get the ingestion URL carrying the site's public key.
 */
function ingestUrl(Site $site): string
{
    return route('api.events.store', ['site_key' => $site->site_key]);
}

beforeEach(function () {
    $this->travelTo(now()->startOfMinute());
});

test('a valid batch is stored for the site and returns 202', function () {
    $campaign = Campaign::factory()->create();
    $site = $campaign->site;

    $response = $this->postJson(ingestUrl($site), ['events' => [
        trackingEvent(),
        trackingEvent(['type' => 'campaign_impression', 'campaign_id' => $campaign->public_id]),
        trackingEvent(['type' => 'conversion', 'campaign_id' => $campaign->public_id, 'properties' => ['value' => 49.9, 'plan' => 'pro']]),
    ]]);

    $response->assertAccepted()->assertExactJson(['accepted' => 3, 'duplicates' => 0, 'rejected' => []]);
    expect(IngestedEvent::count())->toBe(3);
    $conversion = IngestedEvent::where('type', 'conversion')->sole();
    expect($conversion->workspace_id)->toBe($site->workspace_id);
    expect($conversion->site_id)->toBe($site->id);
    expect($conversion->campaign_id)->toBe($campaign->id);
    expect($conversion->status)->toBe(EventStatus::Pending);
    expect($conversion->properties)->toBe(['value' => 49.9, 'plan' => 'pro']);
});

test('resending a batch stores nothing new and reports duplicates', function () {
    $site = Site::factory()->create();
    $events = [trackingEvent(), trackingEvent()];
    $this->postJson(ingestUrl($site), ['events' => $events]);

    $response = $this->postJson(ingestUrl($site), ['events' => $events]);

    $response->assertAccepted()->assertJson(['accepted' => 0, 'duplicates' => 2]);
    expect(IngestedEvent::count())->toBe(2);
});

test('the same event twice in one batch is stored once', function () {
    $site = Site::factory()->create();
    $event = trackingEvent();

    $response = $this->postJson(ingestUrl($site), ['events' => [$event, $event]]);

    $response->assertAccepted()->assertJson(['accepted' => 1, 'duplicates' => 1]);
    expect(IngestedEvent::count())->toBe(1);
});

test('an event already stored by a concurrent request is counted as a duplicate', function () {
    $site = Site::factory()->create();
    $event = trackingEvent();
    IngestedEvent::factory()->for($site)->create(['event_id' => $event['event_id']]);

    $response = $this->postJson(ingestUrl($site), ['events' => [$event]]);

    $response->assertAccepted()->assertJson(['accepted' => 0, 'duplicates' => 1]);
    expect(IngestedEvent::count())->toBe(1);
});

test('the database rejects a second row with the same event ID for a site', function () {
    $existing = IngestedEvent::factory()->create();

    expect(fn () => IngestedEvent::factory()->for($existing->site)->create(['event_id' => $existing->event_id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('event IDs only need to be unique per site', function () {
    $event = trackingEvent();
    $this->postJson(ingestUrl(Site::factory()->create()), ['events' => [$event]]);

    $response = $this->postJson(ingestUrl(Site::factory()->create()), ['events' => [$event]]);

    $response->assertJson(['accepted' => 1]);
    expect(IngestedEvent::count())->toBe(2);
});

test('a JSON batch sent as text/plain is accepted', function () {
    $site = Site::factory()->create();

    $response = $this->call(
        'POST',
        ingestUrl($site),
        server: ['CONTENT_TYPE' => 'text/plain;charset=UTF-8', 'HTTP_ACCEPT' => 'application/json'],
        content: json_encode(['events' => [trackingEvent()]]),
    );

    $response->assertAccepted()->assertJson(['accepted' => 1]);
});

test('an unknown site key returns 401 and stores nothing', function () {
    $response = $this->postJson(route('api.events.store', ['site_key' => 'pk_unknown']), [
        'events' => [trackingEvent()],
    ]);

    $response->assertUnauthorized()->assertJson(['message' => 'Unknown site key.']);
    expect(IngestedEvent::count())->toBe(0);
});

test('a browser origin outside the site domain returns 403 and stores nothing', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);

    $response = $this->postJson(ingestUrl($site), ['events' => [trackingEvent()]], ['Origin' => 'https://attacker.test']);

    $response->assertForbidden();
    expect(IngestedEvent::count())->toBe(0);
});

test('a browser origin on the site domain is accepted', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);

    $response = $this->postJson(ingestUrl($site), ['events' => [trackingEvent()]], ['Origin' => 'https://www.example.com']);

    $response->assertAccepted();
});

test('a request without events returns 422', function () {
    $site = Site::factory()->create();

    $response = $this->postJson(ingestUrl($site), []);

    $response->assertUnprocessable()->assertJsonValidationErrors(['events' => 'The events field is required.']);
});

test('a batch larger than the limit returns 422 and stores nothing', function () {
    config(['tracking.ingest.max_batch_size' => 2]);
    $site = Site::factory()->create();

    $response = $this->postJson(ingestUrl($site), ['events' => [trackingEvent(), trackingEvent(), trackingEvent()]]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['events' => 'The events field must not have more than 2 items.']);
    expect(IngestedEvent::count())->toBe(0);
});

test('a payload larger than the limit returns 413', function () {
    config(['tracking.ingest.max_payload_bytes' => 100]);
    $site = Site::factory()->create();

    $response = $this->postJson(ingestUrl($site), ['events' => [trackingEvent(), trackingEvent()]]);

    $response->assertStatus(413);
    expect(IngestedEvent::count())->toBe(0);
});

test('requests beyond the per-minute limit return 429', function () {
    config(['tracking.ingest.rate_limit_per_minute' => 2]);
    $site = Site::factory()->create();
    $this->postJson(ingestUrl($site), ['events' => [trackingEvent()]]);
    $this->postJson(ingestUrl($site), ['events' => [trackingEvent()]]);

    $response = $this->postJson(ingestUrl($site), ['events' => [trackingEvent()]]);

    $response->assertTooManyRequests();
    expect(IngestedEvent::count())->toBe(2);
});

test('invalid events are rejected individually while valid ones are stored', function (array $invalidEvent, string $field, string $message) {
    $site = Site::factory()->create();

    $response = $this->postJson(ingestUrl($site), ['events' => [trackingEvent(), $invalidEvent]]);

    $response->assertAccepted()
        ->assertJsonPath('accepted', 1)
        ->assertJsonPath('rejected.0.index', 1);
    expect($response->json('rejected.0.errors')[$field])->toBe([$message]);
    expect(IngestedEvent::count())->toBe(1);
})->with([
    'missing event ID' => [fn () => trackingEvent(['event_id' => null]), 'event_id', 'The event id field is required.'],
    'malformed event ID' => [fn () => trackingEvent(['event_id' => 'abc']), 'event_id', 'The event id field must be a valid UUID.'],
    'unknown type' => [fn () => trackingEvent(['type' => 'purchase']), 'type', 'The selected type is invalid.'],
    'impression without campaign' => [fn () => trackingEvent(['type' => 'campaign_impression']), 'campaign_id', 'The campaign id field is required.'],
    'unknown campaign' => [fn () => trackingEvent(['type' => 'campaign_click', 'campaign_id' => (string) Str::ulid()]), 'campaign_id', 'The campaign does not exist on this site.'],
    'nested property' => [fn () => trackingEvent(['properties' => ['cart' => ['id' => 1]]]), 'properties.cart', 'Event properties must be strings, numbers, booleans or null.'],
]);

test('a campaign belonging to another site is rejected', function () {
    $site = Site::factory()->create();
    $otherCampaign = Campaign::factory()->create();

    $response = $this->postJson(ingestUrl($site), ['events' => [
        trackingEvent(['type' => 'campaign_click', 'campaign_id' => $otherCampaign->public_id]),
    ]]);

    $response->assertJsonPath('rejected.0.errors.campaign_id.0', 'The campaign does not exist on this site.');
    expect(IngestedEvent::count())->toBe(0);
});

test('events older than the maximum age are rejected', function () {
    config(['tracking.ingest.max_event_age_hours' => 24]);
    $site = Site::factory()->create();

    $response = $this->postJson(ingestUrl($site), ['events' => [
        trackingEvent(['occurred_at' => now()->subHours(25)->toIso8601String()]),
    ]]);

    $response->assertJsonPath('accepted', 0)->assertJsonPath('rejected.0.index', 0);
    expect(IngestedEvent::count())->toBe(0);
});

test('event times in the future are clamped to the time they were received', function () {
    $site = Site::factory()->create();

    $this->postJson(ingestUrl($site), ['events' => [
        trackingEvent(['occurred_at' => now()->addHour()->toIso8601String()]),
    ]]);

    $event = IngestedEvent::sole();
    expect($event->occurred_at->equalTo(now()))->toBeTrue();
    expect($event->type)->toBe(EventType::PageView);
});
