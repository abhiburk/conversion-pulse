<?php

use App\Support\DateRange;
use Carbon\CarbonImmutable;

test('the 24 hour preset covers the last 24 whole hours including the current one', function () {
    $range = DateRange::preset('24h', CarbonImmutable::parse('2026-10-19 14:35:00', 'UTC'));

    expect($range->from->toDateTimeString())->toBe('2026-10-18 15:00:00');
    expect($range->to->toDateTimeString())->toBe('2026-10-19 15:00:00');
    expect($range->granularity)->toBe('hour');
    expect($range->buckets())->toHaveCount(24);
});

test('day presets cover whole days including today', function (string $key, string $from, int $days) {
    $range = DateRange::preset($key, CarbonImmutable::parse('2026-10-19 14:35:00', 'UTC'));

    expect($range->from->toDateTimeString())->toBe($from);
    expect($range->to->toDateTimeString())->toBe('2026-10-20 00:00:00');
    expect($range->granularity)->toBe('day');
    expect($range->buckets())->toHaveCount($days);
})->with([
    '7 days' => ['7d', '2026-10-13 00:00:00', 7],
    '30 days' => ['30d', '2026-09-20 00:00:00', 30],
]);

test('custom ranges up to two days are charted per hour and longer ones per day', function (string $lastDay, string $granularity, int $buckets) {
    $range = DateRange::days(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse($lastDay));

    expect($range->granularity)->toBe($granularity);
    expect($range->buckets())->toHaveCount($buckets);
})->with([
    'single day' => ['2026-10-01', 'hour', 24],
    'two days' => ['2026-10-02', 'hour', 48],
    'three days' => ['2026-10-03', 'day', 3],
]);

test('the previous range has the same length and ends where the range starts', function () {
    $range = DateRange::preset('7d', CarbonImmutable::parse('2026-10-19 14:35:00', 'UTC'));

    $previous = $range->previous();

    expect($previous->from->toDateTimeString())->toBe('2026-10-06 00:00:00');
    expect($previous->to->toDateTimeString())->toBe('2026-10-13 00:00:00');
});

test('an unknown preset is rejected', function () {
    DateRange::preset('1y', CarbonImmutable::now());
})->throws(InvalidArgumentException::class);
