<script setup lang="ts">
import { useElementSize } from '@vueuse/core';
import { computed, ref, useTemplateRef } from 'vue';
import { niceScale } from '@/components/analytics/niceScale';
import { formatCount } from '@/lib/format';

export type ColumnPoint = {
    key: string;
    axisLabel: string;
    detailLabel: string;
    value: number;
};

const props = defineProps<{
    points: ColumnPoint[];
    label: string;
}>();

const HEIGHT = 240;
const MARGIN = { top: 12, right: 4, bottom: 28, left: 44 };
const MAX_BAR_WIDTH = 24;
const GAP = 2;
const RADIUS = 4;

const container = useTemplateRef<HTMLElement>('container');
const { width } = useElementSize(container);
const activeIndex = ref<number | null>(null);

const plotWidth = computed(() =>
    Math.max(0, width.value - MARGIN.left - MARGIN.right),
);
const plotHeight = HEIGHT - MARGIN.top - MARGIN.bottom;
const baseline = MARGIN.top + plotHeight;

const scale = computed(() =>
    niceScale(Math.max(0, ...props.points.map((point) => point.value))),
);
const band = computed(() =>
    props.points.length > 0 ? plotWidth.value / props.points.length : 0,
);
const barWidth = computed(() =>
    Math.max(1, Math.min(MAX_BAR_WIDTH, band.value - GAP)),
);

function y(value: number): number {
    return MARGIN.top + plotHeight - (value / scale.value.max) * plotHeight;
}

function bandStart(index: number): number {
    return MARGIN.left + index * band.value;
}

/**
 * A column with a rounded data end and a square baseline.
 */
function columnPath(index: number, value: number): string {
    const height = baseline - y(value);

    if (height <= 0) {
        return '';
    }

    const x = bandStart(index) + (band.value - barWidth.value) / 2;
    const top = baseline - height;
    const r = Math.min(RADIUS, barWidth.value / 2, height);

    return [
        `M${x},${baseline}`,
        `V${top + r}`,
        `Q${x},${top} ${x + r},${top}`,
        `H${x + barWidth.value - r}`,
        `Q${x + barWidth.value},${top} ${x + barWidth.value},${top + r}`,
        `V${baseline}`,
        'Z',
    ].join(' ');
}

const labelEvery = computed(() =>
    Math.max(
        1,
        Math.ceil(props.points.length / Math.max(1, plotWidth.value / 72)),
    ),
);

const active = computed(() =>
    activeIndex.value === null ? null : props.points[activeIndex.value],
);

const tooltipLeft = computed(() => {
    if (activeIndex.value === null) {
        return 0;
    }

    const center = bandStart(activeIndex.value) + band.value / 2;

    return Math.min(Math.max(center, 80), Math.max(80, width.value - 80));
});

function onPointerMove(event: PointerEvent): void {
    const bounds = (event.currentTarget as SVGElement).getBoundingClientRect();
    const index = Math.floor(
        (event.clientX - bounds.left - MARGIN.left) / band.value,
    );

    activeIndex.value =
        index >= 0 && index < props.points.length ? index : null;
}

function onKeydown(event: KeyboardEvent): void {
    if (props.points.length === 0) {
        return;
    }

    const last = props.points.length - 1;
    const current = activeIndex.value ?? -1;
    const next =
        event.key === 'ArrowRight'
            ? Math.min(last, current + 1)
            : event.key === 'ArrowLeft'
              ? Math.max(0, current === -1 ? last : current - 1)
              : event.key === 'Home'
                ? 0
                : event.key === 'End'
                  ? last
                  : null;

    if (next !== null) {
        event.preventDefault();
        activeIndex.value = next;
    }
}
</script>

<template>
    <div ref="container" class="relative w-full">
        <svg
            v-if="width > 0"
            :width="width"
            :height="HEIGHT"
            role="img"
            :aria-label="`${label}. Use the left and right arrow keys to read each value.`"
            tabindex="0"
            class="block touch-none rounded-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
            @pointermove="onPointerMove"
            @pointerleave="activeIndex = null"
            @blur="activeIndex = null"
            @keydown="onKeydown"
        >
            <g class="text-border" aria-hidden="true">
                <line
                    v-for="tick in scale.ticks"
                    :key="tick"
                    :x1="MARGIN.left"
                    :x2="MARGIN.left + plotWidth"
                    :y1="y(tick)"
                    :y2="y(tick)"
                    stroke="currentColor"
                    stroke-width="1"
                    shape-rendering="crispEdges"
                />
            </g>
            <g
                class="fill-muted-foreground text-[11px] tabular-nums"
                aria-hidden="true"
            >
                <text
                    v-for="tick in scale.ticks"
                    :key="tick"
                    :x="MARGIN.left - 8"
                    :y="y(tick)"
                    text-anchor="end"
                    dominant-baseline="middle"
                >
                    {{ formatCount(tick) }}
                </text>
                <template v-for="(point, index) in points" :key="point.key">
                    <text
                        v-if="index % labelEvery === 0"
                        :x="bandStart(index) + band / 2"
                        :y="HEIGHT - 8"
                        text-anchor="middle"
                    >
                        {{ point.axisLabel }}
                    </text>
                </template>
            </g>
            <g aria-hidden="true">
                <path
                    v-for="(point, index) in points"
                    :key="point.key"
                    :d="columnPath(index, point.value)"
                    class="transition-colors"
                    :class="
                        index === activeIndex
                            ? 'fill-[#1c5cab] dark:fill-[#5598e7]'
                            : 'fill-[#2a78d6] dark:fill-[#3987e5]'
                    "
                />
            </g>
        </svg>

        <div
            v-if="active"
            class="pointer-events-none absolute top-0 z-10 -translate-x-1/2 rounded-md border bg-popover px-3 py-2 text-popover-foreground shadow-md"
            :style="{ left: `${tooltipLeft}px` }"
            role="status"
        >
            <p class="text-base font-semibold tabular-nums">
                {{ active.value.toLocaleString() }}
            </p>
            <p class="text-xs whitespace-nowrap text-muted-foreground">
                {{ active.detailLabel }}
            </p>
        </div>
    </div>
</template>
