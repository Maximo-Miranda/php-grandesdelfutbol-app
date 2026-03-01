<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club } from '@/types';

type Props = {
    club: Club;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.id}` },
    { title: 'Edit', href: `/clubs/${props.club.id}/edit` },
];

const form = useForm({
    name: props.club.name,
    description: props.club.description ?? '',
    requires_approval: props.club.requires_approval,
    is_invite_active: props.club.is_invite_active,
});

function submit() {
    form.put(`/clubs/${props.club.id}`);
}
</script>

<template>
    <Head :title="`Edit ${club.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4">
            <Heading title="Edit Club" :description="`Update settings for ${club.name}`" />

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
                    <Checkbox id="requires_approval" :checked="form.requires_approval" @update:checked="form.requires_approval = $event" />
                    <Label for="requires_approval">Require approval for new members</Label>
                </div>

                <div class="flex items-center gap-2">
                    <Checkbox id="is_invite_active" :checked="form.is_invite_active" @update:checked="form.is_invite_active = $event" />
                    <Label for="is_invite_active">Enable invite link</Label>
                </div>

                <div class="flex items-center gap-4">
                    <Button type="submit" :disabled="form.processing">Save Changes</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
