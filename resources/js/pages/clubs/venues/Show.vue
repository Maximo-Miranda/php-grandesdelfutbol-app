<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, Venue } from '@/types';

type Props = { club: Club; venue: Venue };
const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.id}` },
    { title: 'Canchas', href: `/clubs/${props.club.id}/venues` },
    { title: props.venue.name, href: `/clubs/${props.club.id}/venues/${props.venue.id}` },
];

const fieldForm = useForm({ name: '', field_type: '5v5', surface_type: '' });

function addField() {
    fieldForm.post(`/clubs/${props.club.id}/venues/${props.venue.id}/fields`, {
        preserveScroll: true,
        onSuccess: () => fieldForm.reset(),
    });
}
</script>

<template>
    <Head :title="venue.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ venue.name }}</h1>
                    <p v-if="venue.address" class="text-sm text-muted-foreground">{{ venue.address }}</p>
                </div>
                <TextLink :href="`/clubs/${club.id}/venues/${venue.id}/edit`">Editar</TextLink>
            </div>

            <!-- Details -->
            <div class="mb-6 rounded-lg border border-border p-4">
                <h3 class="mb-3 font-semibold">Detalles</h3>
                <div class="space-y-2 text-sm">
                    <p><strong>Estado:</strong> <Badge :variant="venue.is_active ? 'default' : 'secondary'">{{ venue.is_active ? 'Activo' : 'Inactivo' }}</Badge></p>
                    <p v-if="venue.address"><strong>Direccion:</strong> {{ venue.address }}</p>
                    <p v-if="venue.map_link"><strong>Mapa:</strong> <a :href="venue.map_link" target="_blank" class="text-primary underline">Ver en mapa</a></p>
                    <p v-if="venue.notes"><strong>Notas:</strong> {{ venue.notes }}</p>
                </div>
            </div>

            <!-- Fields -->
            <div class="rounded-lg border border-border p-4">
                <h3 class="mb-3 font-semibold">Canchas</h3>

                <div v-if="venue.fields && venue.fields.length > 0" class="mb-4 space-y-2">
                    <div v-for="field in venue.fields" :key="field.id" class="flex items-center justify-between rounded-md border border-border p-3">
                        <div>
                            <p class="font-medium">{{ field.name }}</p>
                            <p class="text-sm text-muted-foreground">{{ field.field_type }}<span v-if="field.surface_type"> &middot; {{ field.surface_type }}</span></p>
                        </div>
                        <Badge v-if="!field.is_active" variant="secondary">Inactivo</Badge>
                    </div>
                </div>
                <div v-else class="mb-4 rounded-lg border border-dashed p-4 text-center">
                    <p class="text-sm text-muted-foreground">No hay canchas agregadas.</p>
                </div>

                <form class="space-y-4 border-t border-border pt-4" @submit.prevent="addField">
                    <p class="text-sm font-medium">Agregar cancha</p>
                    <div class="grid gap-2">
                        <Label for="field_name">Nombre</Label>
                        <Input id="field_name" v-model="fieldForm.name" required placeholder="ej. Cancha A" />
                        <InputError :message="fieldForm.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="field_type">Tipo</Label>
                        <Select v-model="fieldForm.field_type">
                            <SelectTrigger id="field_type"><SelectValue placeholder="Seleccionar tipo" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="5v5">5 vs 5</SelectItem>
                                <SelectItem value="6v6">6 vs 6</SelectItem>
                                <SelectItem value="7v7">7 vs 7</SelectItem>
                                <SelectItem value="8v8">8 vs 8</SelectItem>
                                <SelectItem value="9v9">9 vs 9</SelectItem>
                                <SelectItem value="10v10">10 vs 10</SelectItem>
                                <SelectItem value="11v11">11 vs 11</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="fieldForm.errors.field_type" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="surface_type">Superficie</Label>
                        <Input id="surface_type" v-model="fieldForm.surface_type" placeholder="ej. Cesped, Sintetico" />
                        <InputError :message="fieldForm.errors.surface_type" />
                    </div>
                    <Button type="submit" :disabled="fieldForm.processing">Agregar</Button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
