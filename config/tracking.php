<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Event Ingestion
    |--------------------------------------------------------------------------
    |
    | Limits applied to the public event ingestion endpoint used by the
    | tracking SDK. Requests beyond these limits are rejected before any
    | event is stored.
    |
    */

    'ingest' => [
        'max_batch_size' => (int) env('TRACKING_MAX_BATCH_SIZE', 50),
        'max_payload_bytes' => (int) env('TRACKING_MAX_PAYLOAD_BYTES', 64 * 1024),
        'max_properties' => (int) env('TRACKING_MAX_PROPERTIES', 20),
        'max_event_age_hours' => (int) env('TRACKING_MAX_EVENT_AGE_HOURS', 24 * 7),
        'rate_limit_per_minute' => (int) env('TRACKING_RATE_LIMIT_PER_MINUTE', 600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Processing
    |--------------------------------------------------------------------------
    |
    | Hosts that cannot keep a queue worker running (shared hosting) can let
    | the scheduler drain the queue every minute instead. Events are then
    | processed within about a minute rather than within seconds.
    |
    */

    'processing' => [
        'scheduled_worker' => (bool) env('TRACKING_SCHEDULED_QUEUE_WORKER', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Alerts
    |--------------------------------------------------------------------------
    |
    | Thresholds for the scheduled alert checks. A traffic drop compares the
    | last few complete hours with the same hours on previous days, so
    | normal daily patterns (quiet nights) do not trigger alerts.
    |
    */

    'alerts' => [
        'window_hours' => (int) env('ALERTS_WINDOW_HOURS', 3),
        'baseline_days' => (int) env('ALERTS_BASELINE_DAYS', 7),
        'drop_threshold' => (float) env('ALERTS_DROP_THRESHOLD', 0.5),
        'min_baseline_events' => (int) env('ALERTS_MIN_BASELINE_EVENTS', 20),
        'failure_threshold' => (int) env('ALERTS_FAILURE_THRESHOLD', 5),
        'failure_window_minutes' => (int) env('ALERTS_FAILURE_WINDOW_MINUTES', 60),
        'stalled_after_minutes' => (int) env('ALERTS_STALLED_AFTER_MINUTES', 15),
    ],

];
