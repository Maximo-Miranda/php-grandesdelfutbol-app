<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { MapPin } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, Venue } from '@/types';

type Props = { club: Club; venue: Venue };
const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
    { title: 'Canchas', href: `/clubs/${props.club.ulid}/venues` },
    { title: 'Editar', href: `/clubs/${props.club.ulid}/venues/${props.venue.ulid}/edit` },
];

const form = useForm({
    name: props.venue.name,
    address: props.venue.address ?? '',
    map_link: props.venue.map_link ?? '',
    notes: props.venue.notes ?? '',
    is_active: props.venue.is_active,
});

function submit() {
    form.put(`/clubs/${props.club.ulid}/venues/${props.venue.ulid}`);
}
</script>

<template>
    <Head :title="`Editar ${venue.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <Heading title="Editar cancha" />

            <form class="mt-6 space-y-5" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label for="name">Nombre <span class="text-destructive">*</span></Label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <MapPin class="size-4 text-muted-foreground" />
                        </div>
                        <Input id="name" v-model="form.name" required class="pl-9" />
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
                    <InputError :message="form.errors.map_link" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="notes">Notas</Label>
                    <Textarea id="notes" v-model="form.notes" placeholder="ej. Parqueadero disponible, llegar 10 min antes" rows="3" />
                    <InputError :message="form.errors.notes" />
                </div>

                <div class="flex items-center gap-2">
                    <Checkbox id="is_active" v-model="form.is_active" />
                    <Label for="is_active">Activo</Label>
                </div>

                <Button type="submit" :disabled="form.processing" class="w-full sm:w-auto">Guardar cambios</Button>
            </form>
        </div>
    </AppLayout>
</template>
