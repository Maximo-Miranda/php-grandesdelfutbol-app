<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, Player } from '@/types';

type Props = { club: Club; player: Player };
const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.id}` },
    { title: 'Players', href: `/clubs/${props.club.id}/players` },
    { title: 'Edit', href: `/clubs/${props.club.id}/players/${props.player.id}/edit` },
];

const form = useForm({
    name: props.player.name,
    position: props.player.position ?? '',
    jersey_number: props.player.jersey_number ?? '',
    is_active: props.player.is_active,
});

function submit() {
    form.put(`/clubs/${props.club.id}/players/${props.player.id}`);
}
</script>

<template>
    <Head :title="`Edit ${player.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4">
            <Heading title="Edit Player" />
            <form class="mt-6 space-y-6" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="position">Position</Label>
                    <Input id="position" v-model="form.position" />
                    <InputError :message="form.errors.position" />
                </div>
                <div class="grid gap-2">
                    <Label for="jersey_number">Jersey Number</Label>
                    <Input id="jersey_number" v-model="form.jersey_number" type="number" min="1" max="99" />
                    <InputError :message="form.errors.jersey_number" />
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox id="is_active" :checked="form.is_active" @update:checked="form.is_active = $event" />
                    <Label for="is_active">Active</Label>
                </div>
                <Button type="submit" :disabled="form.processing">Save Changes</Button>
            </form>
        </div>
    </AppLayout>
</template>
