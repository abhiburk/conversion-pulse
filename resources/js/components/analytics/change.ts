export type Change = { text: string; direction: 'up' | 'down' | 'flat' };

/**
 * Relative change between two counts, or null when there is no previous value to compare with.
 */
export function countChange(current: number, previous: number): Change | null {
    if (previous === 0) {
        return current === 0 ? { text: 'No change', direction: 'flat' } : null;
    }

    const percent = Math.round(((current - previous) / previous) * 100);

    if (percent === 0) {
        return { text: 'No change', direction: 'flat' };
    }

    return {
        text: `${percent > 0 ? '+' : ''}${percent}%`,
        direction: percent > 0 ? 'up' : 'down',
    };
}

/**
 * Change between two rates in percentage points (a rate going from 2% to 3% is "+1 pt", not "+50%").
 */
export function rateChange(
    current: number | null,
    previous: number | null,
): Change | null {
    if (current === null || previous === null) {
        return null;
    }

    const points = Math.round((current - previous) * 1000) / 10;

    if (points === 0) {
        return { text: 'No change', direction: 'flat' };
    }

    return {
        text: `${points > 0 ? '+' : ''}${points.toLocaleString()} pt`,
        direction: points > 0 ? 'up' : 'down',
    };
}
