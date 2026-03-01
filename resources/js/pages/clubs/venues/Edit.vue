<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, Venue } from '@/types';

type Props = { club: Club; venue: Venue };
const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.id}` },
    { title: 'Venues', href: `/clubs/${props.club.id}/venues` },
    { title: 'Edit', href: `/clubs/${props.club.id}/venues/${props.venue.id}/edit` },
];

const form = useForm({
    name: props.venue.name,
    address: props.venue.address ?? '',
    map_link: props.venue.map_link ?? '',
    notes: props.venue.notes ?? '',
    is_active: props.venue.is_active,
});

function submit() {
    form.put(`/clubs/${props.club.id}/venues/${props.venue.id}`);
}
</script>

<template>
    <Head :title="`Edit ${venue.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4">
            <Heading title="Edit Venue" />
            <form class="mt-6 space-y-6" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="address">Address</Label>
                    <Input id="address" v-model="form.address" placeholder="Street address" />
                    <InputError :message="form.errors.address" />
                </div>
                <div class="grid gap-2">
                    <Label for="map_link">Map Link</Label>
                    <Input id="map_link" v-model="form.map_link" placeholder="https://maps.google.com/..." />
                    <InputError :message="form.errors.map_link" />
                </div>
                <div class="grid gap-2">
                    <Label for="notes">Notes</Label>
                    <Input id="notes" v-model="form.notes" placeholder="Additional information" />
                    <InputError :message="form.errors.notes" />
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
