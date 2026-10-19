<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ChevronDown, CircleAlert, CircleCheck, RotateCcw } from '@lucide/vue';
import EventPipelineController from '@/actions/App/Http/Controllers/EventPipelineController';
import FailedEventRetryController from '@/actions/App/Http/Controllers/FailedEventRetryController';
import Heading from '@/components/Heading.vue';
import StatTile from '@/components/StatTile.vue';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    formatCount,
    formatDateTime,
    formatEventType,
    timeAgo,
} from '@/lib/format';
import type { FailedEvent, Paginated, PipelineStats } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Event pipeline', href: EventPipelineController.index() },
        ],
    },
});

defineProps<{
    stats: PipelineStats;
    failedEvents: Paginated<FailedEvent>;
}>();
</script>

<template>
    <Head title="Event pipeline" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Event pipeline"
            description="Every tracked event is stored first, then processed into analytics by a background worker"
        />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatTile
                label="Waiting to be processed"
                :value="formatCount(stats.pending)"
                :hint="
                    stats.oldestPendingAt
                        ? `Oldest received ${timeAgo(stats.oldestPendingAt)}`
                        : 'Queue is clear'
                "
            />
            <StatTile
                label="Processed in the last 24 hours"
                :value="formatCount(stats.processedLastDay)"
            />
            <StatTile label="Failed" :value="formatCount(stats.failed)">
                <template #hint>
                    <span
                        v-if="stats.failed > 0"
                        class="inline-flex items-center gap-1.5"
                    >
                        <CircleAlert class="size-4 text-[#d03b3b]" />
                        Needs attention
                    </span>
                    <span v-else class="inline-flex items-center gap-1.5">
                        <CircleCheck class="size-4 text-[#0ca30c]" />
                        All healthy
                    </span>
                </template>
            </StatTile>
            <StatTile
                label="Retries"
                value="Safe"
                hint="Each event is counted once, however often it is retried"
            />
        </div>

        <section class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading
                    variant="small"
                    title="Failed events"
                    description="Events the worker gave up on after its automatic retries"
                />
                <Form
                    v-if="stats.failed > 0"
                    v-bind="FailedEventRetryController.storeAll.form()"
                    :options="{ preserveScroll: true }"
                    v-slot="{ processing }"
                >
                    <Button variant="outline" :disabled="processing">
                        <RotateCcw class="size-4" />
                        Retry all {{ formatCount(stats.failed) }}
                    </Button>
                </Form>
            </div>

            <div
                v-if="failedEvents.data.length === 0"
                class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed p-10 text-center"
            >
                <CircleCheck class="size-8 text-muted-foreground" />
                <p class="font-medium">No failed events</p>
                <p class="text-sm text-muted-foreground">
                    Events that cannot be processed will show up here with their
                    error.
                </p>
            </div>

            <ul v-else class="divide-y rounded-xl border">
                <li v-for="event in failedEvents.data" :key="event.id">
                    <Collapsible v-slot="{ open }">
                        <div
                            class="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <CollapsibleTrigger
                                class="flex min-w-0 flex-1 items-start gap-3 text-left"
                            >
                                <ChevronDown
                                    class="mt-0.5 size-4 shrink-0 text-muted-foreground transition-transform"
                                    :class="{ '-rotate-90': !open }"
                                />
                                <div class="min-w-0 space-y-1">
                                    <p class="font-medium capitalize">
                                        {{ formatEventType(event.type) }}
                                        <span
                                            class="font-normal text-muted-foreground normal-case"
                                        >
                                            ·
                                            {{ event.campaign ?? event.site }}
                                        </span>
                                    </p>
                                    <p class="text-sm break-words">
                                        {{ event.last_error }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ event.attempts }}
                                        {{
                                            event.attempts === 1
                                                ? 'attempt'
                                                : 'attempts'
                                        }}
                                        <template v-if="event.failed_at">
                                            · failed
                                            {{ timeAgo(event.failed_at) }}
                                        </template>
                                    </p>
                                </div>
                            </CollapsibleTrigger>
                            <Form
                                v-bind="
                                    FailedEventRetryController.store.form(
                                        event.id,
                                    )
                                "
                                :options="{ preserveScroll: true }"
                                v-slot="{ processing }"
                                class="shrink-0 pl-7 sm:pl-0"
                            >
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    :disabled="processing"
                                >
                                    <RotateCcw class="size-4" />
                                    Retry
                                </Button>
                            </Form>
                        </div>
                        <CollapsibleContent>
                            <dl
                                class="grid gap-x-6 gap-y-2 border-t bg-muted/30 px-4 py-3 pl-11 text-sm sm:grid-cols-[auto_1fr]"
                            >
                                <dt class="text-muted-foreground">Event ID</dt>
                                <dd class="font-mono text-xs break-all">
                                    {{ event.event_id }}
                                </dd>
                                <dt class="text-muted-foreground">Site</dt>
                                <dd>{{ event.site }}</dd>
                                <dt class="text-muted-foreground">Occurred</dt>
                                <dd>{{ formatDateTime(event.occurred_at) }}</dd>
                                <template v-if="event.url">
                                    <dt class="text-muted-foreground">Page</dt>
                                    <dd class="break-all">{{ event.url }}</dd>
                                </template>
                                <template v-if="event.properties">
                                    <dt class="text-muted-foreground">
                                        Properties
                                    </dt>
                                    <dd>
                                        <pre
                                            class="overflow-x-auto font-mono text-xs"
                                            >{{
                                                JSON.stringify(
                                                    event.properties,
                                                    null,
                                                    2,
                                                )
                                            }}</pre>
                                    </dd>
                                </template>
                            </dl>
                        </CollapsibleContent>
                    </Collapsible>
                </li>
            </ul>

            <nav
                v-if="failedEvents.last_page > 1"
                class="flex items-center justify-between text-sm"
            >
                <span class="text-muted-foreground">
                    Page {{ failedEvents.current_page }} of
                    {{ failedEvents.last_page }}
                </span>
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="!failedEvents.prev_page_url"
                        as-child
                    >
                        <Link
                            :href="failedEvents.prev_page_url ?? ''"
                            preserve-scroll
                            >Previous</Link
                        >
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="!failedEvents.next_page_url"
                        as-child
                    >
                        <Link
                            :href="failedEvents.next_page_url ?? ''"
                            preserve-scroll
                            >Next</Link
                        >
                    </Button>
                </div>
            </nav>
        </section>
    </div>
</template>
