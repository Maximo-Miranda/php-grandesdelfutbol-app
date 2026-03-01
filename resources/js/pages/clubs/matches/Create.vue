<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Bell, Clock, MapPin, Pencil, Plus, Trophy, WandSparkles } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, Field, Venue } from '@/types';

type Props = { club: Club; venues: Venue[] };
const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.id}` },
    { title: 'Partidos', href: `/clubs/${props.club.id}/matches` },
    { title: 'Crear', href: `/clubs/${props.club.id}/matches/create` },
];

// Build flat list of fields with venue context
const allFields = computed(() => {
    const fields: Array<{ label: string; field: Field }> = [];
    for (const venue of props.venues) {
        for (const field of venue.fields || []) {
            if (field.is_active) {
                fields.push({
                    label: `${venue.name} - ${field.name} (${field.field_type})`,
                    field,
                });
            }
        }
    }
    return fields;
});

// Field type to max players mapping
const fieldTypeToPlayers: Record<string, number> = {
    '5v5': 10,
    '6v6': 12,
    '7v7': 14,
    '8v8': 16,
    '9v9': 18,
    '10v10': 20,
    '11v11': 22,
};

function resolveFieldType(label: string): string | null {
    if (!label || label === 'none') return null;
    return allFields.value.find((f) => f.label === label)?.field.field_type ?? null;
}

function resolveFieldId(label: string): number | null {
    if (!label || label === 'none') return null;
    return allFields.value.find((f) => f.label === label)?.field.id ?? null;
}

function calcRegistrationHours(maxPlayers: number): number {
    return Math.round(maxPlayers * 2.4);
}

// Time options: 30-min intervals from 06:00 to 23:30
const timeOptions = Array.from({ length: 36 }, (_, i) => {
    const h = Math.floor(i / 2) + 6;
    const m = (i % 2) * 30;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
});

// Smart default date: enough days ahead to organize based on player count
function getDefaultDate(maxPlayers: number): string {
    const today = new Date();
    let daysAhead;
    if (maxPlayers >= 20) daysAhead = 7;
    else if (maxPlayers >= 16) daysAhead = 5;
    else daysAhead = 3;

    const target = new Date(today);
    target.setDate(target.getDate() + daysAhead);

    // Snap to nearest Saturday if within 2 days
    const day = target.getDay();
    const daysToSat = (6 - day + 7) % 7;
    if (daysToSat > 0 && daysToSat <= 2) {
        target.setDate(target.getDate() + daysToSat);
    }

    return `${target.getFullYear()}-${String(target.getMonth() + 1).padStart(2, '0')}-${String(target.getDate()).padStart(2, '0')}`;
}
// Auto-title logic
const autoTitle = ref(true);
const selectedFieldLabel = ref('none');
const selectedDate = ref(getDefaultDate(10));
const selectedTime = ref('10:00');

function formatShortDate(dateStr: string): string {
    const [year, month, day] = dateStr.split('-').map(Number);
    const date = new Date(year, month - 1, day);
    const weekday = date.toLocaleDateString('es', { weekday: 'short' });
    const num = date.getDate();
    const monthName = date.toLocaleDateString('es', { month: 'short' });
    const dayLabel = weekday.charAt(0).toUpperCase() + weekday.slice(1).replace('.', '');
    const monthLabel = monthName.charAt(0).toUpperCase() + monthName.slice(1).replace('.', '');
    return `${dayLabel} ${num} ${monthLabel}`;
}

const generatedTitle = computed(() => {
    const parts: string[] = ['Partido'];
    const fieldType = resolveFieldType(selectedFieldLabel.value);
    if (fieldType) parts.push(fieldType);
    if (selectedDate.value) parts.push(formatShortDate(selectedDate.value));
    return parts.join(' ');
});

const form = useForm({
    title: 'Partido',
    scheduled_at: '',
    field_id: null as number | null,
    duration_minutes: 60,
    arrival_minutes: 15,
    max_players: 10,
    max_substitutes: 4,
    registration_opens_hours: calcRegistrationHours(10),
    notes: '',
});

// Initialize title with default date
form.title = generatedTitle.value;

// Watch auto-title changes
watch(generatedTitle, (val) => {
    if (autoTitle.value) {
        form.title = val;
    }
});

// Watch field selection to autofill max_players, default date, and registration hours
watch(selectedFieldLabel, (label) => {
    const fieldType = resolveFieldType(label);
    if (fieldType && fieldTypeToPlayers[fieldType]) {
        form.max_players = fieldTypeToPlayers[fieldType];
        form.registration_opens_hours = calcRegistrationHours(fieldTypeToPlayers[fieldType]);
        selectedDate.value = getDefaultDate(fieldTypeToPlayers[fieldType]);
    }
    form.field_id = resolveFieldId(label);
});

function enableManualTitle() {
    autoTitle.value = false;
}

function enableAutoTitle() {
    autoTitle.value = true;
    form.title = generatedTitle.value;
}

function submit() {
    // Always resolve values explicitly before posting
    form.scheduled_at = selectedDate.value && selectedTime.value
        ? `${selectedDate.value}T${selectedTime.value}`
        : '';
    form.field_id = resolveFieldId(selectedFieldLabel.value);

    form.post(`/clubs/${props.club.id}/matches`);
}
</script>

<template>
    <Head title="Crear partido" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <TextLink :href="`/clubs/${club.id}/matches`" class="mb-4 inline-flex items-center gap-1 text-sm">
                <ArrowLeft class="size-4" />
                Volver
            </TextLink>

            <h1 class="text-2xl font-bold">Crear Partido</h1>
            <p class="mb-6 text-sm text-muted-foreground">Programa un partido y comparte el link para confirmar asistencia.</p>

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
                        <Plus class="size-4" />
                        Crear partido
                    </Button>
                    <Button variant="outline" as-child>
                        <Link :href="`/clubs/${club.id}/matches`">
                            Cancelar
                        </Link>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
