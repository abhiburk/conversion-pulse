<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import CampaignController from '@/actions/App/Http/Controllers/CampaignController';
import SiteController from '@/actions/App/Http/Controllers/SiteController';
import CampaignStatusSelect from '@/components/CampaignStatusSelect.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { CampaignStatus, SiteSummary } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Campaigns', href: CampaignController.index() },
            { title: 'New campaign', href: CampaignController.create() },
        ],
    },
});

defineProps<{
    sites: SiteSummary[];
    statuses: CampaignStatus[];
}>();
</script>

<template>
    <Head title="New campaign" />

    <div class="flex max-w-xl flex-col gap-6 p-4 md:p-6">
        <Heading
            title="New campaign"
            description="The campaign ID is generated for you and used by the tracking SDK"
        />

        <div
            v-if="sites.length === 0"
            class="space-y-3 rounded-xl border border-dashed p-6"
        >
            <p class="text-sm text-muted-foreground">
                Campaigns run on a site. Add a site first.
            </p>
            <Button as-child>
                <Link :href="SiteController.create()">Add site</Link>
            </Button>
        </div>

        <Form
            v-else
            v-bind="CampaignController.store.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    placeholder="Exit-intent discount popup"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="site_id">Site</Label>
                <Select
                    name="site_id"
                    :default-value="
                        sites.length === 1 ? String(sites[0].id) : undefined
                    "
                >
                    <SelectTrigger id="site_id" class="w-full">
                        <SelectValue placeholder="Choose a site" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="site in sites"
                            :key="site.id"
                            :value="String(site.id)"
                        >
                            {{ site.name }} ({{ site.domain }})
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.site_id" />
            </div>

            <CampaignStatusSelect
                :statuses="statuses"
                default-value="draft"
                :error="errors.status"
            />

            <div class="flex items-center gap-3">
                <Button :disabled="processing">Create campaign</Button>
                <Button variant="ghost" as-child>
                    <Link :href="CampaignController.index()">Cancel</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
