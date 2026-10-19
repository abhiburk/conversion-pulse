<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    CircleCheck,
    Hourglass,
    OctagonAlert,
    TrendingDown,
} from '@lucide/vue';
import AlertAcknowledgementController from '@/actions/App/Http/Controllers/AlertAcknowledgementController';
import AlertController from '@/actions/App/Http/Controllers/AlertController';
import CampaignController from '@/actions/App/Http/Controllers/CampaignController';
import EventPipelineController from '@/actions/App/Http/Controllers/EventPipelineController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime, timeAgo } from '@/lib/format';
import type { AlertType, MonitoringAlert, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Alerts', href: AlertController.index() }],
    },
});

defineProps<{
    openAlerts: MonitoringAlert[];
    resolvedAlerts: Paginated<MonitoringAlert>;
}>();

const icons: Record<AlertType, typeof Activity> = {
    volume_drop: TrendingDown,
    processing_failures: OctagonAlert,
    processing_stalled: Hourglass,
};

function subject(alert: MonitoringAlert): string {
    return alert.campaign
        ? `${alert.campaign.name} · ${alert.site.name}`
        : `${alert.site.name} · ${alert.site.domain}`;
}
</script>

<template>
    <Head title="Alerts" />

    <div class="flex flex-1 flex-col gap-8 p-4 md:p-6">
        <Heading
            title="Alerts"
            description="Checked every 15 minutes. Alerts resolve themselves once tracking is back to normal."
        />

        <section class="space-y-4">
            <Heading variant="small" :title="`Open (${openAlerts.length})`" />

            <div
                v-if="openAlerts.length === 0"
                class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed p-10 text-center"
            >
                <CircleCheck class="size-8 text-[#0ca30c]" />
                <p class="font-medium">All clear</p>
                <p class="text-sm text-muted-foreground">
                    Traffic and event processing look normal.
                </p>
            </div>

            <ul v-else class="space-y-3">
                <li
                    v-for="alert in openAlerts"
                    :key="alert.id"
                    class="flex flex-col gap-4 rounded-xl border border-l-4 border-l-[#d03b3b] p-4 sm:flex-row sm:items-start"
                >
                    <component
                        :is="icons[alert.type]"
                        class="size-5 shrink-0 text-[#d03b3b]"
                        aria-hidden="true"
                    />
                    <div class="min-w-0 flex-1 space-y-1">
                        <p class="font-medium">
                            {{ alert.label }}
                            <span class="font-normal text-muted-foreground"
                                >· {{ subject(alert) }}</span
                            >
                        </p>
                        <p class="text-sm">{{ alert.summary }}</p>
                        <p class="text-xs text-muted-foreground">
                            Opened {{ timeAgo(alert.triggered_at) }} · last
                            checked {{ timeAgo(alert.last_evaluated_at) }}
                            <template v-if="alert.acknowledged_at">
                                · acknowledged by
                                {{ alert.acknowledged_by ?? 'a former member' }}
                                {{ timeAgo(alert.acknowledged_at) }}
                            </template>
                        </p>
                        <div class="flex flex-wrap gap-3 pt-1 text-sm">
                            <Link
                                v-if="alert.campaign"
                                :href="
                                    CampaignController.show(alert.campaign.id, {
                                        query: { range: '24h' },
                                    })
                                "
                                class="underline underline-offset-4"
                                >View campaign traffic</Link
                            >
                            <Link
                                v-if="alert.type !== 'volume_drop'"
                                :href="EventPipelineController.index()"
                                class="underline underline-offset-4"
                                >Open event pipeline</Link
                            >
                        </div>
                    </div>
                    <Form
                        v-if="!alert.acknowledged_at"
                        v-bind="
                            AlertAcknowledgementController.store.form(alert.id)
                        "
                        :options="{ preserveScroll: true }"
                        v-slot="{ processing }"
                        class="shrink-0"
                    >
                        <Button
                            size="sm"
                            variant="outline"
                            :disabled="processing"
                        >
                            Acknowledge
                        </Button>
                    </Form>
                </li>
            </ul>
        </section>

        <section class="space-y-4">
            <Heading variant="small" title="Resolved" />

            <p
                v-if="resolvedAlerts.data.length === 0"
                class="text-sm text-muted-foreground"
            >
                No resolved alerts yet.
            </p>

            <ul v-else class="divide-y rounded-xl border">
                <li
                    v-for="alert in resolvedAlerts.data"
                    :key="alert.id"
                    class="flex flex-col gap-1 p-4 sm:flex-row sm:items-start sm:justify-between sm:gap-4"
                >
                    <div class="min-w-0 space-y-1">
                        <p class="text-sm font-medium">
                            {{ alert.label }}
                            <span class="font-normal text-muted-foreground"
                                >· {{ subject(alert) }}</span
                            >
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ alert.summary }}
                        </p>
                    </div>
                    <p
                        class="shrink-0 text-xs text-muted-foreground sm:text-right"
                    >
                        {{ formatDateTime(alert.triggered_at) }} →
                        <br class="hidden sm:inline" />
                        {{
                            alert.resolved_at
                                ? formatDateTime(alert.resolved_at)
                                : ''
                        }}
                    </p>
                </li>
            </ul>

            <nav
                v-if="resolvedAlerts.last_page > 1"
                class="flex items-center justify-between text-sm"
            >
                <span class="text-muted-foreground">
                    Page {{ resolvedAlerts.current_page }} of
                    {{ resolvedAlerts.last_page }}
                </span>
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="!resolvedAlerts.prev_page_url"
                        as-child
                    >
                        <Link
                            :href="resolvedAlerts.prev_page_url ?? ''"
                            preserve-scroll
                            >Previous</Link
                        >
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="!resolvedAlerts.next_page_url"
                        as-child
                    >
                        <Link
                            :href="resolvedAlerts.next_page_url ?? ''"
                            preserve-scroll
                            >Next</Link
                        >
                    </Button>
                </div>
            </nav>
        </section>
    </div>
</template>
