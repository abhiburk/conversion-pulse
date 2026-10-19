<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { CampaignStatus } from '@/types';

const props = defineProps<{
    status: CampaignStatus;
}>();

const classes: Record<CampaignStatus, string> = {
    draft: 'border-border bg-muted text-muted-foreground',
    active: 'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
    paused: 'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
    archived: 'border-border bg-transparent text-muted-foreground line-through',
};

const label = computed(
    () => props.status.charAt(0).toUpperCase() + props.status.slice(1),
);
</script>

<template>
    <Badge variant="outline" :class="classes[status]">{{ label }}</Badge>
</template>
