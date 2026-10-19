<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { computed, ref } from 'vue';
import CampaignController from '@/actions/App/Http/Controllers/CampaignController';
import { countChange, rateChange } from '@/components/analytics/change';
import FunnelBars from '@/components/analytics/FunnelBars.vue';
import MetricTile from '@/components/analytics/MetricTile.vue';
import RangePicker from '@/components/analytics/RangePicker.vue';
import TrendCard from '@/components/analytics/TrendCard.vue';
import CampaignStatusBadge from '@/components/CampaignStatusBadge.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { useAnalyticsRange } from '@/composables/useAnalyticsRange';
import { formatCount, formatPercent } from '@/lib/format';
import type {
    Analytics,
    AnalyticsFilters,
    Campaign,
    CountMetric,
} from '@/types';

const props = defineProps<{
    campaign: Omit<Campaign, 'created_at'>;
    filters: AnalyticsFilters;
    analytics: Analytics;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Campaigns', href: CampaignController.index() },
        {
            title: props.campaign.name,
            href: CampaignController.show(props.campaign.id),
        },
    ],
});

const { loading, apply } = useAnalyticsRange(() =>
    CampaignController.show.url(props.campaign.id),
);

const metricLabels: Partial<Record<CountMetric, string>> = {
    impressions: 'Impressions',
    clicks: 'Clicks',
    conversions: 'Conversions',
};

const selectedMetric = ref<CountMetric>('impressions');

const countTiles = computed(() =>
    (['impressions', 'clicks', 'conversions'] as const).map((metric) => ({
        metric,
        label: metricLabels[metric] ?? metric,
        value: formatCount(props.analytics.totals[metric]),
        change: countChange(
            props.analytics.totals[metric],
            props.analytics.previous[metric],
        ),
    })),
);
</script>

<template>
    <Head :title="campaign.name" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                <Heading
                    :title="campaign.name"
                    :description="`${campaign.site.name} · ${campaign.site.domain}`"
                />
                <CampaignStatusBadge :status="campaign.status" class="mt-1" />
            </div>
            <Button variant="outline" as-child>
                <Link :href="CampaignController.edit(campaign.id)">
                    <Pencil class="size-4" />
                    Edit
                </Link>
            </Button>
        </div>

        <RangePicker :filters="filters" @change="apply" />

        <div
            class="flex flex-col gap-6 transition-opacity"
            :class="{ 'opacity-60': loading }"
            :aria-busy="loading"
        >
            <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
                <MetricTile
                    v-for="tile in countTiles"
                    :key="tile.metric"
                    :label="tile.label"
                    :value="tile.value"
                    :change="tile.change"
                    selectable
                    :selected="selectedMetric === tile.metric"
                    @select="selectedMetric = tile.metric"
                />
                <MetricTile
                    label="Click-through rate"
                    :value="formatPercent(analytics.totals.click_through_rate)"
                    :change="
                        rateChange(
                            analytics.totals.click_through_rate,
                            analytics.previous.click_through_rate,
                        )
                    "
                />
                <MetricTile
                    label="Conversion rate"
                    :value="formatPercent(analytics.totals.conversion_rate)"
                    :change="
                        rateChange(
                            analytics.totals.conversion_rate,
                            analytics.previous.conversion_rate,
                        )
                    "
                />
            </div>

            <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
                <TrendCard
                    :series="analytics.series"
                    :metric="selectedMetric"
                    :metric-label="metricLabels[selectedMetric] ?? ''"
                    :filters="filters"
                />
                <FunnelBars
                    :impressions="analytics.totals.impressions"
                    :clicks="analytics.totals.clicks"
                    :conversions="analytics.totals.conversions"
                />
            </div>
        </div>
    </div>
</template>
