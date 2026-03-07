<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club } from '@/types';

type PositionOption = { value: string; label: string };
type Props = { club: Club; positions: PositionOption[] };
const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
    { title: 'Players', href: `/clubs/${props.club.ulid}/players` },
    { title: 'Add', href: `/clubs/${props.club.ulid}/players/create` },
];

const form = useForm({ name: '', position: '', jersey_number: '' });

function submit() {
    form.transform((data) => ({
        ...data,
        position: data.position === 'none' ? null : data.position,
    })).post(`/clubs/${props.club.ulid}/players`);
}
</script>

<template>
    <Head title="Add Player" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4">
            <Heading title="Add Player" description="Add a new player to the roster" />
            <form class="mt-6 space-y-6" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="position">Position</Label>
                    <Select v-model="form.position">
                        <SelectTrigger id="position">
                            <SelectValue placeholder="Seleccionar posicion" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">Sin posicion</SelectItem>
                            <SelectItem v-for="pos in positions" :key="pos.value" :value="pos.value">
                                {{ pos.label }} ({{ pos.value }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.position" />
                </div>
                <div class="grid gap-2">
                    <Label for="jersey_number">Jersey Number</Label>
                    <Input id="jersey_number" v-model="form.jersey_number" type="number" min="1" max="99" />
                    <InputError :message="form.errors.jersey_number" />
                </div>
                <Button type="submit" :disabled="form.processing">Add Player</Button>
            </form>
        </div>
    </AppLayout>
</template>
