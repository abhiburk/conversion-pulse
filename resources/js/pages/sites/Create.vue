<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import SiteController from '@/actions/App/Http/Controllers/SiteController';
import Heading from '@/components/Heading.vue';
import SiteFormFields from '@/components/SiteFormFields.vue';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sites', href: SiteController.index() },
            { title: 'Add site', href: SiteController.create() },
        ],
    },
});
</script>

<template>
    <Head title="Add site" />

    <div class="flex max-w-xl flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Add site"
            description="Each site gets its own public key for the tracking snippet"
        />

        <Form
            v-bind="SiteController.store.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <SiteFormFields :errors="errors" />

            <div class="flex items-center gap-3">
                <Button :disabled="processing">Add site</Button>
                <Button variant="ghost" as-child>
                    <Link :href="SiteController.index()">Cancel</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
