<script setup lang="ts">
import { computed, ref } from 'vue';
import ColumnChart from '@/components/analytics/ColumnChart.vue';
import { Button } from '@/components/ui/button';
import { formatBucket, formatBucketRange } from '@/lib/format';
import type { AnalyticsFilters, CountMetric, SeriesPoint } from '@/types';

const props = defineProps<{
    series: SeriesPoint[];
    metric: CountMetric;
    metricLabel: string;
    filters: AnalyticsFilters;
}>();

const view = ref<'chart' | 'table'>('chart');

const per = computed(() =>
    props.filters.granularity === 'hour' ? 'hour' : 'day',
);

const points = computed(() =>
    props.series.map((point) => ({
        key: point.bucket,
        axisLabel: formatBucket(point.bucket, props.filters.granularity),
        detailLabel: `${props.metricLabel} · ${formatBucketRange(point.bucket, props.filters.granularity)}`,
        value: point[props.metric],
    })),
);
</script>

<template>
    <section class="rounded-xl border p-4">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-medium">{{ metricLabel }} per {{ per }}</h2>
                <p class="text-sm text-muted-foreground">Times shown in UTC</p>
            </div>
            <div
                class="inline-flex rounded-lg border p-0.5"
                role="group"
                aria-label="Display as"
            >
                <Button
                    size="sm"
                    :variant="view === 'chart' ? 'secondary' : 'ghost'"
                    :aria-pressed="view === 'chart'"
                    @click="view = 'chart'"
                >
                    Chart
                </Button>
                <Button
                    size="sm"
                    :variant="view === 'table' ? 'secondary' : 'ghost'"
                    :aria-pressed="view === 'table'"
                    @click="view = 'table'"
                >
                    Table
                </Button>
            </div>
        </div>

        <ColumnChart
            v-if="view === 'chart'"
            :points="points"
            :label="`${metricLabel} per ${per}`"
        />

        <div v-else class="max-h-72 overflow-auto">
            <table class="w-full text-sm">
                <thead
                    class="sticky top-0 bg-background text-left text-muted-foreground"
                >
                    <tr class="border-b">
                        <th class="py-2 pr-4 font-normal">
                            {{ per === 'hour' ? 'Hour (UTC)' : 'Day (UTC)' }}
                        </th>
                        <th class="py-2 pr-4 text-right font-normal">
                            Page views
                        </th>
                        <th class="py-2 pr-4 text-right font-normal">
                            Impressions
                        </th>
                        <th class="py-2 pr-4 text-right font-normal">Clicks</th>
                        <th class="py-2 text-right font-normal">Conversions</th>
                    </tr>
                </thead>
                <tbody class="tabular-nums">
                    <tr
                        v-for="point in series"
                        :key="point.bucket"
                        class="border-b last:border-0"
                    >
                        <td class="py-1.5 pr-4">
                            {{
                                formatBucketRange(
                                    point.bucket,
                                    filters.granularity,
                                )
                            }}
                        </td>
                        <td class="py-1.5 pr-4 text-right">
                            {{ point.page_views.toLocaleString() }}
                        </td>
                        <td class="py-1.5 pr-4 text-right">
                            {{ point.impressions.toLocaleString() }}
                        </td>
                        <td class="py-1.5 pr-4 text-right">
                            {{ point.clicks.toLocaleString() }}
                        </td>
                        <td class="py-1.5 text-right">
                            {{ point.conversions.toLocaleString() }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
