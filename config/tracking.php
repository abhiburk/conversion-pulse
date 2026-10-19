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

];
