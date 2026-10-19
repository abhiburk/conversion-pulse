<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import CampaignController from '@/actions/App/Http/Controllers/CampaignController';
import CampaignStatusBadge from '@/components/CampaignStatusBadge.vue';
import CampaignStatusSelect from '@/components/CampaignStatusSelect.vue';
import CopyableValue from '@/components/CopyableValue.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Campaign, CampaignStatus } from '@/types';

const props = defineProps<{
    campaign: Omit<Campaign, 'created_at'>;
    statuses: CampaignStatus[];
}>();

const usageExample = [
    `ConversionPulse.impression('${props.campaign.public_id}');`,
    `ConversionPulse.click('${props.campaign.public_id}');`,
    `ConversionPulse.conversion({ campaignId: '${props.campaign.public_id}', properties: { value: 49 } });`,
].join('\n');

setLayoutProps({
    breadcrumbs: [
        { title: 'Campaigns', href: CampaignController.index() },
        {
            title: props.campaign.name,
            href: CampaignController.edit(props.campaign.id),
        },
    ],
});
</script>

<template>
    <Head :title="campaign.name" />

    <div class="flex max-w-xl flex-col gap-10 p-4 md:p-6">
        <section class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <Heading
                    :title="campaign.name"
                    :description="`${campaign.site.name} · ${campaign.site.domain}`"
                />
                <CampaignStatusBadge :status="campaign.status" />
            </div>

            <div class="space-y-2">
                <Heading
                    variant="small"
                    title="Campaign ID"
                    description="Pass this ID to the tracking SDK for impression, click and conversion events."
                />
                <CopyableValue
                    :value="campaign.public_id"
                    label="campaign ID"
                />
            </div>
        </section>

        <section class="space-y-6">
            <Heading variant="small" title="Details" />

            <Form
                v-bind="CampaignController.update.form(campaign.id)"
                class="space-y-6"
                :options="{ preserveScroll: true }"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        name="name"
                        :default-value="campaign.name"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>

                <CampaignStatusSelect
                    :statuses="statuses"
                    :default-value="campaign.status"
                    :error="errors.status"
                />

                <Button :disabled="processing">Save</Button>
            </Form>
        </section>
    </div>
</template>
