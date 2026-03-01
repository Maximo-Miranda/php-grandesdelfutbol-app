<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club } from '@/types';

type Props = { club: Club };
const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.id}` },
    { title: 'Venues', href: `/clubs/${props.club.id}/venues` },
    { title: 'Add', href: `/clubs/${props.club.id}/venues/create` },
];

const form = useForm({ name: '', address: '', map_link: '', notes: '' });

function submit() {
    form.post(`/clubs/${props.club.id}/venues`);
}
</script>

<template>
    <Head title="Add Venue" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4">
            <Heading title="Add Venue" description="Add a new venue for your club" />
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
                <Button type="submit" :disabled="form.processing">Add Venue</Button>
            </form>
        </div>
    </AppLayout>
</template>
