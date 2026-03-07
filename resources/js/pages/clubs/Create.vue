<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: 'Create', href: '/clubs/create' },
];

const form = useForm({
    name: '',
    description: '',
    requires_approval: false,
});

function submit() {
    form.post('/clubs');
}
</script>

<template>
    <Head title="Create Club" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4">
            <Heading title="Create Club" description="Set up a new football club" />

            <form class="mt-6 space-y-6" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name">Club Name</Label>
                    <Input id="name" v-model="form.name" required placeholder="Enter club name" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="description">Description</Label>
                    <Input id="description" v-model="form.description" placeholder="Optional description" />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="flex items-center gap-2">
                    <Checkbox id="requires_approval" v-model="form.requires_approval" />
                    <Label for="requires_approval">Require approval for new members</Label>
                </div>

                <Button type="submit" :disabled="form.processing">Create Club</Button>
            </form>
        </div>
    </AppLayout>
</template>
