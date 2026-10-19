<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Globe, Plus } from '@lucide/vue';
import SiteController from '@/actions/App/Http/Controllers/SiteController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import type { Site } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Sites', href: SiteController.index() }],
    },
});

defineProps<{
    sites: (Site & { campaigns_count: number })[];
}>();
</script>

<template>
    <Head title="Sites" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Sites"
                description="Websites where the Conversion Pulse tracker is installed"
            />
            <Button as-child>
                <Link :href="SiteController.create()">
                    <Plus class="size-4" />
                    Add site
                </Link>
            </Button>
        </div>

        <div
            v-if="sites.length === 0"
            class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed p-12 text-center"
        >
            <Globe class="size-8 text-muted-foreground" />
            <div>
                <p class="font-medium">No sites yet</p>
                <p class="text-sm text-muted-foreground">
                    Add a site to get a site key for the tracking snippet.
                </p>
            </div>
        </div>

        <ul v-else class="divide-y rounded-xl border">
            <li v-for="site in sites" :key="site.id">
                <Link
                    :href="SiteController.edit(site.id)"
                    class="flex flex-col gap-1 p-4 transition-colors hover:bg-muted/50 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ site.name }}</p>
                        <p class="truncate text-sm text-muted-foreground">
                            {{ site.domain }}
                        </p>
                    </div>
                    <p class="shrink-0 text-sm text-muted-foreground">
                        {{ site.campaigns_count }}
                        {{
                            site.campaigns_count === 1
                                ? 'campaign'
                                : 'campaigns'
                        }}
                    </p>
                </Link>
            </li>
        </ul>
    </div>
</template>
