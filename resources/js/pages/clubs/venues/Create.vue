<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { MapPin } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club } from '@/types';

type Props = { club: Club };
const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
    { title: 'Canchas', href: `/clubs/${props.club.ulid}/venues` },
    { title: 'Agregar', href: `/clubs/${props.club.ulid}/venues/create` },
];

const form = useForm({ name: '', address: '', map_link: '', notes: '' });

function submit() {
    form.post(`/clubs/${props.club.ulid}/venues`);
}
</script>

<template>
    <Head title="Agregar cancha" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <Heading title="Agregar cancha" description="Agrega un lugar donde se juegan los partidos de tu club." />

            <form class="mt-6 space-y-5" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label for="name">Nombre <span class="text-destructive">*</span></Label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <MapPin class="size-4 text-muted-foreground" />
                        </div>
                        <Input id="name" v-model="form.name" required placeholder="ej. Cancha Brazileirao" class="pl-9" />
                    </div>
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="address">Direccion</Label>
                    <Input id="address" v-model="form.address" placeholder="ej. Calle 4a # 13-39" />
                    <InputError :message="form.errors.address" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="map_link">Enlace de mapa</Label>
                    <Input id="map_link" v-model="form.map_link" placeholder="https://maps.google.com/..." />
                    <p class="text-xs text-muted-foreground">Pega un enlace de Google Maps para que los jugadores encuentren el lugar facilmente.</p>
                    <InputError :message="form.errors.map_link" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="notes">Notas</Label>
                    <Textarea id="notes" v-model="form.notes" placeholder="ej. Parqueadero disponible, llegar 10 min antes" rows="3" />
                    <InputError :message="form.errors.notes" />
                </div>

                <Button type="submit" :disabled="form.processing" class="w-full sm:w-auto">Agregar cancha</Button>
            </form>
        </div>
    </AppLayout>
</template>
