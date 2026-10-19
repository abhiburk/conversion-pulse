<script setup lang="ts">
import { ArrowDownRight, ArrowUpRight, Minus } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    label: string;
    value: string;
    /** Change versus the previous period, already formatted (e.g. "+12%"), with its direction. */
    change?: { text: string; direction: 'up' | 'down' | 'flat' } | null;
    selectable?: boolean;
    selected?: boolean;
}>();

defineEmits<{ select: [] }>();

const changeClass = computed(() => {
    switch (props.change?.direction) {
        case 'up':
            return 'text-emerald-700 dark:text-emerald-400';
        case 'down':
            return 'text-red-700 dark:text-red-400';
        default:
            return 'text-muted-foreground';
    }
});
</script>

<template>
    <component
        :is="selectable ? 'button' : 'div'"
        :type="selectable ? 'button' : undefined"
        :aria-pressed="selectable ? selected : undefined"
        class="flex flex-col gap-1 rounded-xl border p-4 text-left transition-colors"
        :class="[
            selectable ? 'cursor-pointer hover:bg-muted/50' : '',
            selected
                ? 'border-[#2a78d6] ring-1 ring-[#2a78d6] dark:border-[#3987e5] dark:ring-[#3987e5]'
                : '',
        ]"
        @click="selectable && $emit('select')"
    >
        <span class="text-sm text-muted-foreground">{{ label }}</span>
        <span class="text-2xl font-semibold tracking-tight">{{ value }}</span>
        <span
            class="inline-flex min-h-5 items-center gap-1 text-xs"
            :class="changeClass"
        >
            <template v-if="change">
                <ArrowUpRight
                    v-if="change.direction === 'up'"
                    class="size-3.5"
                />
                <ArrowDownRight
                    v-else-if="change.direction === 'down'"
                    class="size-3.5"
                />
                <Minus v-else class="size-3.5" />
                {{ change.text }}
                <span class="text-muted-foreground">vs previous period</span>
            </template>
        </span>
    </component>
</template>
