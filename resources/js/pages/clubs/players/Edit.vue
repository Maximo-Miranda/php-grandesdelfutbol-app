<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, Player } from '@/types';

type PositionOption = { value: string; label: string };
type Props = { club: Club; player: Player; positions: PositionOption[]; isAdmin: boolean };
const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
    { title: 'Jugadores', href: `/clubs/${props.club.ulid}/players` },
    { title: 'Editar', href: `/clubs/${props.club.ulid}/players/${props.player.ulid}/edit` },
];

const form = useForm({
    name: props.player.name,
    position: props.player.position ?? 'none',
    jersey_number: props.player.jersey_number ?? '',
    is_active: props.player.is_active,
});

function submit() {
    form.transform((data) => {
        const transformed: Record<string, unknown> = {
            name: data.name,
            position: data.position === 'none' ? null : data.position,
            jersey_number: data.jersey_number === '' ? null : Number(data.jersey_number),
        };
        if (props.isAdmin) {
            transformed.is_active = !!data.is_active;
        }

        return transformed;
    }).put(`/clubs/${props.club.ulid}/players/${props.player.ulid}`);
}
</script>

<template>
    <Head :title="`Editar ${player.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4">
            <Heading title="Editar jugador" />
            <form class="mt-6 space-y-6" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name">Nombre</Label>
                    <Input id="name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="position">Posicion</Label>
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
                    <Label for="jersey_number">Numero de camiseta</Label>
                    <Input id="jersey_number" v-model="form.jersey_number" type="number" min="1" max="99" />
                    <InputError :message="form.errors.jersey_number" />
                </div>
                <div v-if="isAdmin" class="flex items-center gap-2">
                    <Checkbox id="is_active" v-model="form.is_active" />
                    <Label for="is_active">Activo</Label>
                </div>
                <Button type="submit" :disabled="form.processing">Guardar cambios</Button>
            </form>
        </div>
    </AppLayout>
</template>
