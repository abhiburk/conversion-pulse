<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BellRing, ChartColumn, ShieldCheck } from '@lucide/vue';
import DemoStoreController from '@/actions/App/Http/Controllers/DemoStoreController';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

const features = [
    {
        icon: ShieldCheck,
        title: 'Reliable ingestion',
        description:
            'Every event is stored durably and counted exactly once, even when browsers retry or requests race.',
    },
    {
        icon: ChartColumn,
        title: 'Campaign analytics',
        description:
            'Impressions, clicks, conversions and rates per campaign, for any time range.',
    },
    {
        icon: BellRing,
        title: 'Tracking alerts',
        description:
            'Know when a campaign stops receiving traffic or events stop processing, before your reports go wrong.',
    },
];
</script>

<template>
    <Head title="Campaign tracking you can trust" />

    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header
            class="mx-auto flex w-full max-w-5xl items-center justify-between p-4 md:p-6"
        >
            <div class="flex items-center gap-2 font-semibold">
                <span
                    class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground"
                >
                    <AppLogoIcon class="size-5" />
                </span>
                {{ $page.props.name }}
            </div>
            <nav class="flex items-center gap-2">
                <Button v-if="$page.props.auth.user" as-child>
                    <Link :href="dashboard()">Dashboard</Link>
                </Button>
                <template v-else>
                    <Button variant="ghost" as-child>
                        <Link :href="login()">Log in</Link>
                    </Button>
                    <Button as-child>
                        <Link :href="register()">Get started</Link>
                    </Button>
                </template>
            </nav>
        </header>

        <main
            class="mx-auto flex w-full max-w-5xl flex-1 flex-col justify-center gap-12 p-4 py-12 md:p-6"
        >
            <section class="max-w-2xl space-y-5">
                <h1
                    class="text-4xl font-semibold tracking-tight text-balance md:text-5xl"
                >
                    Campaign tracking you can trust
                </h1>
                <p class="text-lg text-muted-foreground">
                    Conversion Pulse collects popup and form events from your
                    sites, turns them into campaign analytics, and alerts you
                    when tracking breaks.
                </p>
                <div class="flex flex-wrap gap-3">
                    <Button size="lg" as-child>
                        <Link :href="register()">Create a workspace</Link>
                    </Button>
                    <Button size="lg" variant="outline" as-child>
                        <a :href="DemoStoreController.url()"
                            >Try the demo store</a
                        >
                    </Button>
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-3">
                <div
                    v-for="feature in features"
                    :key="feature.title"
                    class="space-y-2 rounded-xl border p-5"
                >
                    <component
                        :is="feature.icon"
                        class="size-5 text-muted-foreground"
                    />
                    <h2 class="font-medium">{{ feature.title }}</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ feature.description }}
                    </p>
                </div>
            </section>
        </main>
    </div>
</template>
