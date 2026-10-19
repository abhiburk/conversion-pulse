<script setup lang="ts">
import { computed } from 'vue';
import { formatPercent } from '@/lib/format';

const props = defineProps<{
    impressions: number;
    clicks: number;
    conversions: number;
}>();

const steps = computed(() => {
    const top = Math.max(props.impressions, props.clicks, props.conversions);

    return [
        { label: 'Impressions', value: props.impressions, note: null },
        {
            label: 'Clicks',
            value: props.clicks,
            note:
                props.impressions > 0
                    ? `${formatPercent(props.clicks / props.impressions)} of impressions`
                    : null,
        },
        {
            label: 'Conversions',
            value: props.conversions,
            note:
                props.impressions > 0
                    ? `${formatPercent(props.conversions / props.impressions)} of impressions`
                    : null,
        },
    ].map((step) => ({
        ...step,
        width:
            top > 0
                ? Math.max((step.value / top) * 100, step.value > 0 ? 1 : 0)
                : 0,
    }));
});
</script>

<template>
    <section class="rounded-xl border p-4">
        <h2 class="mb-4 font-medium">Funnel</h2>
        <dl class="space-y-4">
            <div v-for="step in steps" :key="step.label" class="space-y-1.5">
                <div class="flex items-baseline justify-between gap-3 text-sm">
                    <dt class="text-muted-foreground">{{ step.label }}</dt>
                    <dd class="font-medium tabular-nums">
                        {{ step.value.toLocaleString() }}
                        <span
                            v-if="step.note"
                            class="ml-1 font-normal text-muted-foreground"
                            >· {{ step.note }}</span
                        >
                    </dd>
                </div>
                <div class="h-3 rounded-r-[4px] bg-muted/60" aria-hidden="true">
                    <div
                        class="h-3 rounded-r-[4px] bg-[#2a78d6] dark:bg-[#3987e5]"
                        :style="{ width: `${step.width}%` }"
                    />
                </div>
            </div>
        </dl>
    </section>
</template>
