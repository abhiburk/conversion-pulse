<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { RangeParams } from '@/composables/useAnalyticsRange';
import type { AnalyticsFilters, RangeKey } from '@/types';

const props = defineProps<{
    filters: AnalyticsFilters;
}>();

const emit = defineEmits<{ change: [params: RangeParams] }>();

const presets: { key: Exclude<RangeKey, 'custom'>; label: string }[] = [
    { key: '24h', label: '24 hours' },
    { key: '7d', label: '7 days' },
    { key: '30d', label: '30 days' },
];

const page = usePage();
const showCustom = ref(props.filters.key === 'custom');
const today = new Date().toISOString().slice(0, 10);
const from = ref(props.filters.from.slice(0, 10));
const to = ref(lastDay(props.filters.to));

watch(
    () => props.filters,
    (filters) => {
        showCustom.value = filters.key === 'custom';
        from.value = filters.from.slice(0, 10);
        to.value = lastDay(filters.to);
    },
);

/**
 * The range end is exclusive, so the last included day is the one before it.
 */
function lastDay(exclusiveEnd: string): string {
    return new Date(new Date(exclusiveEnd).getTime() - 1)
        .toISOString()
        .slice(0, 10);
}

function applyCustom(): void {
    emit('change', { range: 'custom', from: from.value, to: to.value });
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <div
            class="inline-flex w-fit flex-wrap rounded-lg border p-0.5"
            role="group"
            aria-label="Date range"
        >
            <Button
                v-for="preset in presets"
                :key="preset.key"
                size="sm"
                :variant="
                    filters.key === preset.key && !showCustom
                        ? 'secondary'
                        : 'ghost'
                "
                :aria-pressed="filters.key === preset.key && !showCustom"
                @click="
                    showCustom = false;
                    emit('change', { range: preset.key });
                "
            >
                {{ preset.label }}
            </Button>
            <Button
                size="sm"
                :variant="showCustom ? 'secondary' : 'ghost'"
                :aria-pressed="showCustom"
                @click="showCustom = true"
            >
                Custom
            </Button>
        </div>

        <form
            v-if="showCustom"
            class="flex flex-wrap items-end gap-3"
            @submit.prevent="applyCustom"
        >
            <div class="grid gap-1.5">
                <Label for="range-from" class="text-xs">From</Label>
                <Input
                    id="range-from"
                    v-model="from"
                    type="date"
                    :max="to || today"
                    class="w-40"
                    required
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="range-to" class="text-xs">To</Label>
                <Input
                    id="range-to"
                    v-model="to"
                    type="date"
                    :min="from"
                    :max="today"
                    class="w-40"
                    required
                />
            </div>
            <Button type="submit" size="sm" class="h-9">Apply</Button>
            <InputError
                class="basis-full"
                :message="page.props.errors?.to ?? page.props.errors?.from"
            />
        </form>
    </div>
</template>
