<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Site } from '@/types';

defineProps<{
    site?: Pick<Site, 'name' | 'domain'>;
    errors: Partial<Record<string, string>>;
}>();
</script>

<template>
    <div class="grid gap-2">
        <Label for="name">Name</Label>
        <Input
            id="name"
            name="name"
            :default-value="site?.name"
            required
            placeholder="Marketing site"
        />
        <InputError :message="errors.name" />
    </div>

    <div class="grid gap-2">
        <Label for="domain">Domain</Label>
        <Input
            id="domain"
            name="domain"
            :default-value="site?.domain"
            required
            placeholder="example.com"
            autocapitalize="none"
            spellcheck="false"
        />
        <p class="text-xs text-muted-foreground">
            Paste a URL or a hostname. The scheme and path are removed.
        </p>
        <InputError :message="errors.domain" />
    </div>
</template>
