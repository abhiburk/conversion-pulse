<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import SiteController from '@/actions/App/Http/Controllers/SiteController';
import CopyableValue from '@/components/CopyableValue.vue';
import Heading from '@/components/Heading.vue';
import SiteFormFields from '@/components/SiteFormFields.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { Site } from '@/types';

const props = defineProps<{
    site: Site;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Sites', href: SiteController.index() },
        { title: props.site.name, href: SiteController.edit(props.site.id) },
    ],
});
</script>

<template>
    <Head :title="site.name" />

    <div class="flex max-w-xl flex-col gap-10 p-4 md:p-6">
        <section class="space-y-6">
            <Heading :title="site.name" :description="site.domain" />

            <div class="space-y-2">
                <Heading
                    variant="small"
                    title="Site key"
                    description="Public identifier the tracking SDK sends with every event. It is safe to embed in your pages."
                />
                <CopyableValue :value="props.site.site_key" label="site key" />
            </div>
        </section>

        <section class="space-y-6">
            <Heading variant="small" title="Details" />

            <Form
                v-bind="SiteController.update.form(site.id)"
                class="space-y-6"
                :options="{ preserveScroll: true }"
                v-slot="{ errors, processing }"
            >
                <SiteFormFields :site="site" :errors="errors" />

                <Button :disabled="processing">Save</Button>
            </Form>
        </section>

        <section class="space-y-4">
            <Heading
                variant="small"
                title="Delete site"
                description="Removes the site and all of its campaigns"
            />
            <Dialog>
                <DialogTrigger as-child>
                    <Button variant="destructive">Delete site</Button>
                </DialogTrigger>
                <DialogContent>
                    <Form
                        v-bind="SiteController.destroy.form(site.id)"
                        class="space-y-6"
                        v-slot="{ processing }"
                    >
                        <DialogHeader class="space-y-3">
                            <DialogTitle>Delete {{ site.name }}?</DialogTitle>
                            <DialogDescription>
                                The site and all of its campaigns will be
                                permanently deleted, and events sent with this
                                site key will be rejected.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                :disabled="processing"
                            >
                                Delete site
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </section>
    </div>
</template>
