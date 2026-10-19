<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Globe, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import CampaignController from '@/actions/App/Http/Controllers/CampaignController';
import DashboardController from '@/actions/App/Http/Controllers/DashboardController';
import SiteController from '@/actions/App/Http/Controllers/SiteController';
import { countChange, rateChange } from '@/components/analytics/change';
import MetricTile from '@/components/analytics/MetricTile.vue';
import RangePicker from '@/components/analytics/RangePicker.vue';
import TrendCard from '@/components/analytics/TrendCard.vue';
import CampaignStatusBadge from '@/components/CampaignStatusBadge.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { rangeQuery, useAnalyticsRange } from '@/composables/useAnalyticsRange';
import { formatCount, formatPercent } from '@/lib/format';
import { dashboard } from '@/routes';
import type {
    Analytics,
    AnalyticsFilters,
    CampaignPerformance,
    CountMetric,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const props = defineProps<{
    filters: AnalyticsFilters;
    analytics: Analytics;
    campaigns: CampaignPerformance[];
    hasSites: boolean;
}>();

const { loading, apply } = useAnalyticsRange(() =>
    DashboardController.index.url(),
);

const metricLabels: Record<CountMetric, string> = {
    page_views: 'Page views',
    impressions: 'Impressions',
    clicks: 'Clicks',
    conversions: 'Conversions',
};

const selectedMetric = ref<CountMetric>('impressions');

const countTiles = computed(() =>
    (Object.keys(metricLabels) as CountMetric[]).map((metric) => ({
        metric,
        label: metricLabels[metric],
        value: formatCount(props.analytics.totals[metric]),
        change: countChange(
            props.analytics.totals[metric],
            props.analytics.previous[metric],
        ),
    })),
);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-col gap-4">
            <Heading
                title="Overview"
                description="Processed tracking events across every site in your workspace"
            />
            <RangePicker :filters="filters" @change="apply" />
        </div>

        <div
            v-if="!hasSites"
            class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed p-12 text-center"
        >
            <Globe class="size-8 text-muted-foreground" />
            <div>
                <p class="font-medium">Start by adding a site</p>
                <p class="text-sm text-muted-foreground">
                    Install the tracking snippet and your metrics will appear
                    here.
                </p>
            </div>
            <Button as-child>
                <Link :href="SiteController.create()">Add site</Link>
            </Button>
        </div>

        <div
            v-else
            class="flex flex-col gap-6 transition-opacity"
            :class="{ 'opacity-60': loading }"
            :aria-busy="loading"
        >
            <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
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

            <TrendCard
                :series="analytics.series"
                :metric="selectedMetric"
                :metric-label="metricLabels[selectedMetric]"
                :filters="filters"
            />

            <section class="rounded-xl border">
                <div class="p-4">
                    <h2 class="font-medium">Campaigns</h2>
                    <p class="text-sm text-muted-foreground">
                        Active campaigns and any campaign with events in this
                        range, by impressions
                    </p>
                </div>
                <div
                    v-if="campaigns.length === 0"
                    class="border-t p-6 text-center text-sm text-muted-foreground"
                >
                    No campaign activity in this range.
                    <Link
                        :href="CampaignController.create()"
                        class="text-foreground underline underline-offset-4"
                        >Create a campaign</Link
                    >
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead class="text-left text-muted-foreground">
                            <tr class="border-y">
                                <th class="px-4 py-2 font-normal">Campaign</th>
                                <th class="px-4 py-2 font-normal">Status</th>
                                <th class="px-4 py-2 text-right font-normal">
                                    Impressions
                                </th>
                                <th class="px-4 py-2 text-right font-normal">
                                    Clicks
                                </th>
                                <th class="px-4 py-2 text-right font-normal">
                                    CTR
                                </th>
                                <th class="px-4 py-2 text-right font-normal">
                                    Conversions
                                </th>
                                <th class="px-4 py-2 text-right font-normal">
                                    Conv. rate
                                </th>
                            </tr>
                        </thead>
                        <tbody class="tabular-nums">
                            <tr
                                v-for="campaign in campaigns"
                                :key="campaign.id"
                                class="border-b last:border-0 hover:bg-muted/50"
                            >
                                <td class="px-4 py-2.5">
                                    <Link
                                        :href="
                                            CampaignController.show(
                                                campaign.id,
                                                {
                                                    query: rangeQuery(filters),
                                                },
                                            )
                                        "
                                        class="font-medium hover:underline"
                                    >
                                        {{ campaign.name }}
                                    </Link>
                                    <p class="text-xs text-muted-foreground">
                                        {{ campaign.site }}
                                    </p>
                                </td>
                                <td class="px-4 py-2.5">
                                    <CampaignStatusBadge
                                        :status="campaign.status"
                                    />
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <span
                                        v-if="
                                            campaign.status === 'active' &&
                                            campaign.impressions === 0
                                        "
                                        class="inline-flex items-center gap-1.5"
                                    >
                                        <TriangleAlert
                                            class="size-4 text-[#fab219]"
                                        />
                                        No impressions
                                    </span>
                                    <template v-else>
                                        {{
                                            campaign.impressions.toLocaleString()
                                        }}
                                    </template>
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    {{ campaign.clicks.toLocaleString() }}
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    {{
                                        formatPercent(
                                            campaign.click_through_rate,
                                        )
                                    }}
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    {{ campaign.conversions.toLocaleString() }}
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    {{
                                        formatPercent(campaign.conversion_rate)
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</template>
