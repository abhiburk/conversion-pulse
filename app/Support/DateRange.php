<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A half-open UTC time range [from, to) for analytics, split into hourly or daily buckets.
 */
final readonly class DateRange
{
    /**
     * Ranges up to this many hours are charted per hour; longer ranges per day.
     */
    private const int MAX_HOURLY_SPAN_HOURS = 48;

    /**
     * @param  '24h'|'7d'|'30d'|'custom'  $key
     * @param  'hour'|'day'  $granularity
     */
    public function __construct(
        public string $key,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public string $granularity,
    ) {}

    /**
     * Build one of the preset ranges ending with the current hour or day.
     */
    public static function preset(string $key, CarbonImmutable $now): self
    {
        $now = $now->utc();

        return match ($key) {
            '24h' => new self('24h', $now->startOfHour()->subHours(23), $now->startOfHour()->addHour(), 'hour'),
            '7d' => new self('7d', $now->startOfDay()->subDays(6), $now->startOfDay()->addDay(), 'day'),
            '30d' => new self('30d', $now->startOfDay()->subDays(29), $now->startOfDay()->addDay(), 'day'),
            default => throw new InvalidArgumentException("Unknown range preset [{$key}]."),
        };
    }

    /**
     * Build a range covering whole UTC days, both dates inclusive.
     */
    public static function days(CarbonImmutable $firstDay, CarbonImmutable $lastDay): self
    {
        $from = $firstDay->utc()->startOfDay();
        $to = $lastDay->utc()->startOfDay()->addDay();

        return new self(
            'custom',
            $from,
            $to,
            $from->diffInHours($to) <= self::MAX_HOURLY_SPAN_HOURS ? 'hour' : 'day',
        );
    }

    /**
     * The range of equal length immediately before this one, used for period-over-period comparison.
     */
    public function previous(): self
    {
        $lengthInSeconds = (int) $this->from->diffInSeconds($this->to);

        return new self($this->key, $this->from->subSeconds($lengthInSeconds), $this->from, $this->granularity);
    }

    /**
     * The start of every bucket in the range, in order.
     *
     * @return list<CarbonImmutable>
     */
    public function buckets(): array
    {
        $buckets = [];

        for ($bucket = $this->from; $bucket->lessThan($this->to); $bucket = $this->step($bucket)) {
            $buckets[] = $bucket;
        }

        return $buckets;
    }

    /**
     * The start of the bucket an hourly timestamp falls into.
     */
    public function bucketFor(CarbonImmutable $moment): CarbonImmutable
    {
        return $this->granularity === 'hour' ? $moment->startOfHour() : $moment->startOfDay();
    }

    /**
     * @return array{key: string, from: string, to: string, granularity: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'from' => $this->from->toIso8601String(),
            'to' => $this->to->toIso8601String(),
            'granularity' => $this->granularity,
        ];
    }

    private function step(CarbonImmutable $bucket): CarbonImmutable
    {
        return $this->granularity === 'hour' ? $bucket->addHour() : $bucket->addDay();
    }
}
