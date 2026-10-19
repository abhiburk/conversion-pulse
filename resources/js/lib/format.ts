const compactNumber = new Intl.NumberFormat(undefined, {
    notation: 'compact',
    maximumFractionDigits: 1,
});

/**
 * Format a count for display, compacting large values (1,284 → 1.3K).
 */
export function formatCount(value: number): string {
    return value < 10_000
        ? value.toLocaleString()
        : compactNumber.format(value);
}

/**
 * Describe how long ago an ISO timestamp was, e.g. "3 min ago".
 */
export function timeAgo(iso: string, now: Date = new Date()): string {
    const seconds = Math.max(
        0,
        Math.round((now.getTime() - new Date(iso).getTime()) / 1000),
    );

    if (seconds < 60) {
        return `${seconds}s ago`;
    }

    if (seconds < 3600) {
        return `${Math.floor(seconds / 60)} min ago`;
    }

    if (seconds < 86_400) {
        return `${Math.floor(seconds / 3600)} h ago`;
    }

    return `${Math.floor(seconds / 86_400)} d ago`;
}

export function formatDateTime(iso: string): string {
    return new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

export function formatEventType(type: string): string {
    return type.replace('campaign_', '').replace('_', ' ');
}

/**
 * Format a 0–1 rate as a percentage, or a dash when it is undefined (nothing to divide by).
 */
export function formatPercent(rate: number | null): string {
    if (rate === null) {
        return '—';
    }

    return `${(rate * 100).toLocaleString(undefined, { maximumFractionDigits: 1 })}%`;
}

/**
 * Short axis label for a UTC bucket: "14:00" for hours, "Oct 19" for days.
 */
export function formatBucket(iso: string, granularity: 'hour' | 'day'): string {
    return new Date(iso).toLocaleString(undefined, {
        timeZone: 'UTC',
        ...(granularity === 'hour'
            ? { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }
            : { month: 'short', day: 'numeric' }),
    });
}

/**
 * Full tooltip label for a UTC bucket, e.g. "Oct 19, 14:00–15:00 UTC" or "Sun, Oct 19".
 */
export function formatBucketRange(
    iso: string,
    granularity: 'hour' | 'day',
): string {
    const start = new Date(iso);

    if (granularity === 'day') {
        return start.toLocaleDateString(undefined, {
            timeZone: 'UTC',
            weekday: 'short',
            month: 'short',
            day: 'numeric',
        });
    }

    const end = new Date(start.getTime() + 3_600_000);
    const day = start.toLocaleDateString(undefined, {
        timeZone: 'UTC',
        month: 'short',
        day: 'numeric',
    });

    return `${day}, ${formatBucket(start.toISOString(), 'hour')}–${formatBucket(end.toISOString(), 'hour')} UTC`;
}
