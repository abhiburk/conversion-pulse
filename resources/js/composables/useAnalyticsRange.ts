import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { AnalyticsFilters, RangeKey } from '@/types';

export type RangeParams = { range: RangeKey; from?: string; to?: string };

/**
 * Reload an analytics page for a new date range while keeping the current render on screen.
 */
export function useAnalyticsRange(url: () => string) {
    const loading = ref(false);

    function apply(params: RangeParams): void {
        router.get(url(), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => {
                loading.value = true;
            },
            onFinish: () => {
                loading.value = false;
            },
        });
    }

    return { loading, apply };
}

/**
 * The query parameters that reproduce the current range on another analytics page.
 */
export function rangeQuery(filters: AnalyticsFilters): RangeParams {
    if (filters.key !== 'custom') {
        return { range: filters.key };
    }

    const lastDay = new Date(new Date(filters.to).getTime() - 1);

    return {
        range: 'custom',
        from: filters.from.slice(0, 10),
        to: lastDay.toISOString().slice(0, 10),
    };
}
