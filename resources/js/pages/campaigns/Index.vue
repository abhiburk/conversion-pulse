<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Megaphone, Plus } from '@lucide/vue';
import CampaignController from '@/actions/App/Http/Controllers/CampaignController';
import CampaignStatusBadge from '@/components/CampaignStatusBadge.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import type { Campaign } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Campaigns', href: CampaignController.index() }],
    },
});

defineProps<{
    campaigns: Campaign[];
}>();
</script>

<template>
    <Head title="Campaigns" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Campaigns"
                description="Popups and forms whose impressions, clicks and conversions are tracked"
            />
            <Button as-child>
                <Link :href="CampaignController.create()">
                    <Plus class="size-4" />
                    New campaign
                </Link>
            </Button>
        </div>

        <div
            v-if="campaigns.length === 0"
            class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed p-12 text-center"
        >
            <Megaphone class="size-8 text-muted-foreground" />
            <div>
                <p class="font-medium">No campaigns yet</p>
                <p class="text-sm text-muted-foreground">
                    Create a campaign to start tracking its funnel.
                </p>
            </div>
        </div>

        <ul v-else class="divide-y rounded-xl border">
            <li v-for="campaign in campaigns" :key="campaign.id">
                <Link
                    :href="CampaignController.show(campaign.id)"
                    class="flex flex-col gap-2 p-4 transition-colors hover:bg-muted/50 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ campaign.name }}</p>
                        <p class="truncate text-sm text-muted-foreground">
                            {{ campaign.site.name }} ·
                            {{ campaign.site.domain }}
                        </p>
                    </div>
                    <CampaignStatusBadge :status="campaign.status" />
                </Link>
            </li>
        </ul>
    </div>
</template>
