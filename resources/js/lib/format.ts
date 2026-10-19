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
