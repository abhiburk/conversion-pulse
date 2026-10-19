<script setup lang="ts">
import { useClipboard } from '@vueuse/core';
import { Check, Copy } from '@lucide/vue';
import { Button } from '@/components/ui/button';

defineProps<{
    value: string;
    label: string;
}>();

const { copy, copied } = useClipboard({ copiedDuring: 1500 });
</script>

<template>
    <div
        class="flex items-center gap-2 rounded-md border bg-muted/40 py-1 pr-1 pl-3"
    >
        <code class="flex-1 truncate font-mono text-sm">{{ value }}</code>
        <Button
            type="button"
            variant="ghost"
            size="icon"
            class="size-8"
            :aria-label="`Copy ${label}`"
            @click="copy(value)"
        >
            <Check v-if="copied" class="size-4 text-emerald-600" />
            <Copy v-else class="size-4" />
        </Button>
    </div>
</template>
