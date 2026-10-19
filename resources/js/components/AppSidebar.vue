<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Activity,
    BellRing,
    Globe,
    LayoutGrid,
    Megaphone,
    Store,
} from '@lucide/vue';
import AlertController from '@/actions/App/Http/Controllers/AlertController';
import CampaignController from '@/actions/App/Http/Controllers/CampaignController';
import DemoStoreController from '@/actions/App/Http/Controllers/DemoStoreController';
import EventPipelineController from '@/actions/App/Http/Controllers/EventPipelineController';
import SiteController from '@/actions/App/Http/Controllers/SiteController';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Campaigns',
        href: CampaignController.index(),
        icon: Megaphone,
    },
    {
        title: 'Sites',
        href: SiteController.index(),
        icon: Globe,
    },
    {
        title: 'Event pipeline',
        href: EventPipelineController.index(),
        icon: Activity,
    },
    {
        title: 'Alerts',
        href: AlertController.index(),
        icon: BellRing,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Demo store',
        href: DemoStoreController.url(),
        icon: Store,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
