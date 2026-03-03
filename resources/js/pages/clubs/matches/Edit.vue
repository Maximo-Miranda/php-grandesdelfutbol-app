<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Bell, Clock, MapPin, Palette, Pencil, Save, Trophy, WandSparkles } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { timeOptions, useMatchForm } from '@/composables/useMatchForm';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, FootballMatch, Venue } from '@/types';

type Props = { club: Club; match: FootballMatch; venues: Venue[] };
const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.id}` },
    { title: 'Partidos', href: `/clubs/${props.club.id}/matches` },
    { title: props.match.title, href: `/clubs/${props.club.id}/matches/${props.match.id}` },
    { title: 'Editar', href: `/clubs/${props.club.id}/matches/${props.match.id}/edit` },
];

const {
    form,
    allFields,
    autoTitle,
    selectedFieldLabel,
    selectedDate,
    selectedTime,
    enableManualTitle,
    enableAutoTitle,
    resolveBeforeSubmit,
} = useMatchForm({
    venues: props.venues,
    match: props.match,
    autoTitleOnInit: false,
});

function submit() {
    resolveBeforeSubmit();
    form.put(`/clubs/${props.club.id}/matches/${props.match.id}`);
}
</script>

<template>
    <Head :title="`Editar ${match.title}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <TextLink :href="`/clubs/${club.id}/matches/${match.id}`" class="mb-4 inline-flex items-center gap-1 text-sm">
                <ArrowLeft class="size-4" />
                Volver
            </TextLink>

            <h1 class="text-2xl font-bold">Editar Partido</h1>
            <p class="mb-6 text-sm text-muted-foreground">Modifica los detalles del partido.</p>

            <form class="space-y-5" @submit.prevent="submit">
                <!-- Titulo -->
                <div class="grid gap-1.5">
                    <Label for="title">Titulo <span class="text-destructive">*</span></Label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <Trophy class="size-4 text-muted-foreground" />
                        </div>
                        <Input
                            id="title"
                            v-model="form.title"
                            placeholder="Ej: Pichanga 7v7 Sab 28 Feb"
                            class="pl-9 pr-9"
                            :readonly="autoTitle"
                            :class="autoTitle ? 'bg-muted/50' : ''"
                        />
                        <button
                            type="button"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground hover:text-foreground"
                            @click="autoTitle ? enableManualTitle() : enableAutoTitle()"
                        >
                            <Pencil v-if="autoTitle" class="size-4" />
                            <WandSparkles v-else class="size-4" />
                        </button>
                    </div>
                    <p v-if="autoTitle" class="text-xs text-muted-foreground">
                        Se genera automaticamente. Presiona el lapiz para editar.
                    </p>
                    <InputError :message="form.errors.title" />
                </div>

                <!-- Cancha -->
                <div class="grid gap-1.5">
                    <Label for="field_id">Cancha</Label>
                    <p class="text-xs text-muted-foreground">Selecciona la cancha donde se jugara.</p>
                    <Select v-model="selectedFieldLabel">
                        <SelectTrigger id="field_id">
                            <div class="flex items-center gap-2">
                                <MapPin class="size-4 text-muted-foreground" />
                                <SelectValue placeholder="Sin cancha" />
                            </div>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">Sin cancha</SelectItem>
                            <SelectItem
                                v-for="f in allFields"
                                :key="f.field.id"
                                :value="f.label"
                            >
                                {{ f.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.field_id" />
                </div>

                <!-- Fecha y Hora -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label for="date">Fecha <span class="text-destructive">*</span></Label>
                        <Input
                            id="date"
                            v-model="selectedDate"
                            type="date"
                        />
                        <InputError :message="form.errors.scheduled_at" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="time">Hora <span class="text-destructive">*</span></Label>
                        <Select v-model="selectedTime">
                            <SelectTrigger id="time">
                                <div class="flex items-center gap-2">
                                    <Clock class="size-4 text-muted-foreground" />
                                    <SelectValue placeholder="Seleccionar" />
                                </div>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="t in timeOptions" :key="t" :value="t">
                                    {{ t }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <!-- Duracion y Llegada -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label for="duration_minutes">Duracion (min) <span class="text-destructive">*</span></Label>
                        <Input
                            id="duration_minutes"
                            v-model="form.duration_minutes"
                            type="number"
                        />
                        <InputError :message="form.errors.duration_minutes" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="arrival_minutes">Llegada antes (min)</Label>
                        <Input
                            id="arrival_minutes"
                            v-model="form.arrival_minutes"
                            type="number"
                        />
                        <InputError :message="form.errors.arrival_minutes" />
                    </div>
                </div>

                <!-- Max Jugadores y Suplentes -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label for="max_players">Max jugadores <span class="text-destructive">*</span></Label>
                        <Input
                            id="max_players"
                            v-model="form.max_players"
                            type="number"
                        />
                        <InputError :message="form.errors.max_players" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="max_substitutes">Max suplentes</Label>
                        <Input
                            id="max_substitutes"
                            v-model="form.max_substitutes"
                            type="number"
                        />
                        <InputError :message="form.errors.max_substitutes" />
                    </div>
                </div>

                <!-- Registro abre antes -->
                <div class="grid gap-1.5">
                    <Label for="registration_opens_hours">Registro abre antes (hrs) <span class="text-destructive">*</span></Label>
                    <p class="text-xs text-muted-foreground">Horas antes del partido en que se abre el registro.</p>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <Bell class="size-4 text-muted-foreground" />
                        </div>
                        <Input
                            id="registration_opens_hours"
                            v-model="form.registration_opens_hours"
                            type="number"
                            class="pl-9"
                        />
                    </div>
                    <InputError :message="form.errors.registration_opens_hours" />
                </div>

                <!-- Equipos -->
                <div class="grid gap-1.5">
                    <Label>Equipos</Label>
                    <p class="text-xs text-muted-foreground">Nombres y colores de camiseta para cada equipo.</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-1.5">
                            <Label for="team_a_name">Nombre Equipo A</Label>
                            <Input id="team_a_name" v-model="form.team_a_name" placeholder="Equipo A" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="team_b_name">Nombre Equipo B</Label>
                            <Input id="team_b_name" v-model="form.team_b_name" placeholder="Equipo B" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-1.5">
                            <Label for="team_a_color" class="flex items-center gap-1.5">
                                <Palette class="size-3.5 text-muted-foreground" />
                                Color Equipo A
                            </Label>
                            <div class="flex items-center gap-2">
                                <input
                                    id="team_a_color"
                                    v-model="form.team_a_color"
                                    type="color"
                                    class="size-9 cursor-pointer rounded border border-border"
                                />
                                <span class="text-xs text-muted-foreground">{{ form.team_a_color || 'Sin color' }}</span>
                            </div>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="team_b_color" class="flex items-center gap-1.5">
                                <Palette class="size-3.5 text-muted-foreground" />
                                Color Equipo B
                            </Label>
                            <div class="flex items-center gap-2">
                                <input
                                    id="team_b_color"
                                    v-model="form.team_b_color"
                                    type="color"
                                    class="size-9 cursor-pointer rounded border border-border"
                                />
                                <span class="text-xs text-muted-foreground">{{ form.team_b_color || 'Sin color' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notas -->
                <div class="grid gap-1.5">
                    <Label for="notes">Notas</Label>
                    <p class="text-xs text-muted-foreground">Informacion adicional (reglas, equipamiento, etc.)</p>
                    <Textarea
                        id="notes"
                        v-model="form.notes"
                        placeholder="Informacion util para los jugadores..."
                        rows="3"
                    />
                    <InputError :message="form.errors.notes" />
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-3 pt-2">
                    <Button type="submit" class="flex-1 gap-2" :disabled="form.processing">
                        <Save class="size-4" />
                        Guardar cambios
                    </Button>
                    <Button variant="outline" as-child>
                        <Link :href="`/clubs/${club.id}/matches/${match.id}`">
                            Cancelar
                        </Link>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
