<script setup lang="ts">
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import InputError from '@/components/InputError.vue';
import type { CampaignStatus } from '@/types';

defineProps<{
    statuses: CampaignStatus[];
    defaultValue: CampaignStatus;
    error?: string;
}>();

const labels: Record<CampaignStatus, string> = {
    draft: 'Draft: not expected to receive events yet',
    active: 'Active: live and monitored',
    paused: 'Paused: temporarily off',
    archived: 'Archived: finished',
};
</script>

<template>
    <div class="grid gap-2">
        <Label for="status">Status</Label>
        <Select name="status" :default-value="defaultValue">
            <SelectTrigger id="status" class="w-full">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="status in statuses"
                    :key="status"
                    :value="status"
                >
                    {{ labels[status] }}
                </SelectItem>
            </SelectContent>
        </Select>
        <InputError :message="error" />
    </div>
</template>
