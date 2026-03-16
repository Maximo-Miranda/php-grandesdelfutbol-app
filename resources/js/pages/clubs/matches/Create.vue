<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Bell, Clock, Info, MapPin, Pencil, Plus, Trophy, WandSparkles } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ColorSwatchPicker from '@/components/ColorSwatchPicker.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { timeOptions, useMatchForm } from '@/composables/useMatchForm';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, Venue } from '@/types';

type Props = { club: Club; venues: Venue[] };
const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubes', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
    { title: 'Partidos', href: `/clubs/${props.club.ulid}/matches` },
    { title: 'Crear', href: `/clubs/${props.club.ulid}/matches/create` },
];

const {
    form,
    allFields,
    autoTitle,
    autoTeamA,
    autoTeamB,
    selectedFieldLabel,
    selectedDate,
    selectedTime,
    enableManualTitle,
    enableAutoTitle,
    enableManualTeamName,
    enableAutoTeamName,
    isPastMatch,
    resolveBeforeSubmit,
} = useMatchForm({
    venues: () => props.venues,
});

function submit() {
    resolveBeforeSubmit();
    form.post(`/clubs/${props.club.ulid}/matches`);
}

// --- Inline venue/field creation ---
const showVenueDialog = ref(false);
const dialogMode = ref<'new' | 'existing'>('new');
const selectedVenueUlid = ref('');

const hasVenues = computed(() => props.venues.length > 0);

function openDialog() {
    dialogMode.value = hasVenues.value ? 'existing' : 'new';
    selectedVenueUlid.value = '';
    venueForm.reset();
    fieldForm.reset();
    autoFieldName.value = true;
    fieldForm.field_type = '7v7';
    fieldForm.surface_type = 'sintetico';
    venueForm.field_type = '7v7';
    venueForm.surface_type = 'sintetico';
    showVenueDialog.value = true;
}

const surfaceOptions = [
    { value: 'sintetico', label: 'Sintetico' },
    { value: 'cesped', label: 'Cesped natural' },
    { value: 'tierra', label: 'Tierra' },
    { value: 'concreto', label: 'Concreto' },
    { value: 'otro', label: 'Otro' },
];

const fieldTypeOptions = [
    { value: '5v5', label: '5 vs 5' },
    { value: '6v6', label: '6 vs 6' },
    { value: '7v7', label: '7 vs 7' },
    { value: '8v8', label: '8 vs 8' },
    { value: '9v9', label: '9 vs 9' },
    { value: '10v10', label: '10 vs 10' },
    { value: '11v11', label: '11 vs 11' },
];

// Form for new venue + field
const venueForm = useForm({
    name: '',
    address: '',
    map_link: '',
    field_name: '',
    field_type: '7v7',
    surface_type: 'sintetico',
});

// Form for adding field to existing venue
const fieldForm = useForm({
    name: '',
    field_type: '7v7',
    surface_type: 'sintetico',
});

const autoFieldName = ref(true);

const activeSurfaceType = computed(() => dialogMode.value === 'new' ? venueForm.surface_type : fieldForm.surface_type);
const activeFieldType = computed(() => dialogMode.value === 'new' ? venueForm.field_type : fieldForm.field_type);
const surfaceLabel = computed(() => surfaceOptions.find((o) => o.value === activeSurfaceType.value)?.label ?? activeSurfaceType.value);

const selectedVenueFieldCount = computed(() => {
    const venue = props.venues.find((v) => v.ulid === selectedVenueUlid.value);
    return (venue?.fields?.length ?? 0) + 1;
});

const generatedFieldName = computed(() => {
    const num = dialogMode.value === 'existing' ? selectedVenueFieldCount.value : 1;
    return `Cancha ${num} ${activeFieldType.value} ${surfaceLabel.value}`;
});

watch(generatedFieldName, (val) => {
    if (autoFieldName.value) {
        if (dialogMode.value === 'new') {
            venueForm.field_name = val;
        } else {
            fieldForm.name = val;
        }
    }
});

// Initialize field names
watch(dialogMode, () => {
    autoFieldName.value = true;
    venueForm.field_name = generatedFieldName.value;
    fieldForm.name = generatedFieldName.value;
});

venueForm.field_name = generatedFieldName.value;
fieldForm.name = generatedFieldName.value;

const activeFieldTypeModel = computed({
    get: () => dialogMode.value === 'new' ? venueForm.field_type : fieldForm.field_type,
    set: (v: string) => { if (dialogMode.value === 'new') venueForm.field_type = v; else fieldForm.field_type = v; },
});

const activeSurfaceTypeModel = computed({
    get: () => dialogMode.value === 'new' ? venueForm.surface_type : fieldForm.surface_type,
    set: (v: string) => { if (dialogMode.value === 'new') venueForm.surface_type = v; else fieldForm.surface_type = v; },
});

const activeFieldNameModel = computed({
    get: () => dialogMode.value === 'new' ? venueForm.field_name : fieldForm.name,
    set: (v: string) => { if (dialogMode.value === 'new') venueForm.field_name = v; else fieldForm.name = v; },
});

const pendingAutoSelect = ref(false);

watch(allFields, (fields) => {
    if (pendingAutoSelect.value && fields.length > 0) {
        selectedFieldLabel.value = fields[fields.length - 1].label;
        pendingAutoSelect.value = false;
    }
});

function submitNewVenue() {
    pendingAutoSelect.value = true;
    venueForm.post(`/clubs/${props.club.ulid}/venues/quick-create`, {
        preserveScroll: true,
        onSuccess: () => { showVenueDialog.value = false; },
        onError: () => { pendingAutoSelect.value = false; },
    });
}

function submitExistingVenueField() {
    const venue = props.venues.find((v) => v.ulid === selectedVenueUlid.value);
    if (!venue) return;
    pendingAutoSelect.value = true;
    fieldForm.post(`/clubs/${props.club.ulid}/venues/${venue.ulid}/fields`, {
        preserveScroll: true,
        onSuccess: () => { showVenueDialog.value = false; },
        onError: () => { pendingAutoSelect.value = false; },
    });
}
</script>

<template>
    <Head title="Crear partido" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <h1 class="text-2xl font-bold">Crear Partido</h1>
            <p class="mb-6 text-sm text-muted-foreground">Programa un partido y comparte el link para confirmar asistencia.</p>

            <form class="space-y-5" @submit.prevent="submit">
                <!-- Cancha -->
                <div class="grid gap-1.5">
                    <Label for="field_id">Cancha</Label>
                    <p class="text-xs text-muted-foreground">Selecciona la cancha donde se jugara.</p>
                    <Select v-model="selectedFieldLabel">
                        <SelectTrigger id="field_id" class="min-w-0 overflow-hidden">
                            <MapPin class="size-4 shrink-0 text-muted-foreground" />
                            <SelectValue placeholder="Sin cancha" />
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
                    <Dialog v-model:open="showVenueDialog">
                        <button type="button" class="ml-auto flex items-center gap-1 text-xs text-primary hover:underline" @click="openDialog">
                            <Plus class="size-3" />
                            Agregar nueva cancha
                        </button>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Nueva cancha</DialogTitle>
                                <DialogDescription>Agrega una cancha para poder seleccionarla en el partido.</DialogDescription>
                            </DialogHeader>

                            <!-- Mode toggle -->
                            <div v-if="hasVenues" class="flex gap-1 rounded-lg bg-muted p-1">
                                <button
                                    type="button"
                                    class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                                    :class="dialogMode === 'existing' ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                                    @click="dialogMode = 'existing'"
                                >
                                    Lugar existente
                                </button>
                                <button
                                    type="button"
                                    class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                                    :class="dialogMode === 'new' ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                                    @click="dialogMode = 'new'"
                                >
                                    Nuevo lugar
                                </button>
                            </div>

                            <div class="space-y-4">
                                <!-- Existing venue: select -->
                                <div v-if="dialogMode === 'existing'" class="grid gap-1.5">
                                    <Label for="existing_venue">Lugar <span class="text-destructive">*</span></Label>
                                    <Select v-model="selectedVenueUlid">
                                        <SelectTrigger id="existing_venue">
                                            <MapPin class="size-4 shrink-0 text-muted-foreground" />
                                            <SelectValue placeholder="Selecciona un lugar" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="v in venues" :key="v.ulid" :value="v.ulid">
                                                {{ v.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <!-- New venue: name + address -->
                                <template v-if="dialogMode === 'new'">
                                    <div class="grid gap-1.5">
                                        <Label for="venue_name">Nombre del lugar <span class="text-destructive">*</span></Label>
                                        <div class="relative">
                                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                                <MapPin class="size-4 text-muted-foreground" />
                                            </div>
                                            <Input id="venue_name" v-model="venueForm.name" required placeholder="ej. Cancha Brazileirao" class="pl-9" />
                                        </div>
                                        <InputError :message="venueForm.errors.name" />
                                    </div>

                                    <div class="grid gap-1.5">
                                        <Label for="venue_address">Dirección</Label>
                                        <Input id="venue_address" v-model="venueForm.address" placeholder="ej. Calle 4a # 13-39" />
                                        <InputError :message="venueForm.errors.address" />
                                    </div>

                                    <div class="grid gap-1.5">
                                        <Label for="venue_map_link">Enlace de Google Maps</Label>
                                        <Input id="venue_map_link" v-model="venueForm.map_link" placeholder="https://maps.google.com/..." />
                                        <InputError :message="venueForm.errors.map_link" />
                                    </div>
                                </template>

                                <Separator />

                                <!-- Field details (shared) -->
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="grid gap-1.5">
                                        <Label for="venue_field_type">Tipo de cancha <span class="text-destructive">*</span></Label>
                                        <Select v-model="activeFieldTypeModel">
                                            <SelectTrigger id="venue_field_type"><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="opt in fieldTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="venue_surface_type">Superficie</Label>
                                        <Select v-model="activeSurfaceTypeModel">
                                            <SelectTrigger id="venue_surface_type"><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="opt in surfaceOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div class="grid gap-1.5">
                                    <Label for="venue_field_name">Nombre de la cancha <span class="text-destructive">*</span></Label>
                                    <div class="relative">
                                        <Input
                                            id="venue_field_name"
                                            v-model="activeFieldNameModel"
                                            required
                                            class="pr-9"
                                            :readonly="autoFieldName"
                                            :class="autoFieldName ? 'bg-muted/50' : ''"
                                        />
                                        <button
                                            type="button"
                                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground hover:text-foreground"
                                            @click="autoFieldName = !autoFieldName; if (autoFieldName) activeFieldNameModel = generatedFieldName"
                                        >
                                            <Pencil v-if="autoFieldName" class="size-4" />
                                            <WandSparkles v-else class="size-4" />
                                        </button>
                                    </div>
                                    <p v-if="autoFieldName" class="text-xs text-muted-foreground">
                                        Se genera automaticamente.
                                    </p>
                                </div>
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    :disabled="dialogMode === 'new' ? venueForm.processing : fieldForm.processing || !selectedVenueUlid"
                                    @click="dialogMode === 'new' ? submitNewVenue() : submitExistingVenueField()"
                                >
                                    Crear cancha
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                    <InputError :message="form.errors.field_id" />
                </div>

                <!-- Fecha y Hora -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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

                <!-- Past match alert -->
                <div v-if="isPastMatch" class="flex items-start gap-2 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-200">
                    <Info class="mt-0.5 size-4 shrink-0" />
                    <span>Este partido se creará como finalizado. Podrás confirmar jugadores y cargar estadísticas manualmente.</span>
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
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2 rounded-md border border-border p-3">
                            <div class="relative">
                                <Input
                                    id="team_a_name"
                                    v-model="form.team_a_name"
                                    class="pr-9"
                                    :readonly="autoTeamA"
                                    :class="autoTeamA ? 'bg-muted/50' : ''"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground hover:text-foreground"
                                    @click="autoTeamA ? enableManualTeamName('a') : enableAutoTeamName('a')"
                                >
                                    <Pencil v-if="autoTeamA" class="size-4" />
                                    <WandSparkles v-else class="size-4" />
                                </button>
                            </div>
                            <ColorSwatchPicker v-model="form.team_a_color" />
                        </div>
                        <div class="grid gap-2 rounded-md border border-border p-3">
                            <div class="relative">
                                <Input
                                    id="team_b_name"
                                    v-model="form.team_b_name"
                                    class="pr-9"
                                    :readonly="autoTeamB"
                                    :class="autoTeamB ? 'bg-muted/50' : ''"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground hover:text-foreground"
                                    @click="autoTeamB ? enableManualTeamName('b') : enableAutoTeamName('b')"
                                >
                                    <Pencil v-if="autoTeamB" class="size-4" />
                                    <WandSparkles v-else class="size-4" />
                                </button>
                            </div>
                            <ColorSwatchPicker v-model="form.team_b_color" />
                        </div>
                    </div>
                </div>

                <!-- Notas -->
                <div class="grid gap-1.5">
                    <Label for="notes">Notas</Label>
                    <p class="text-xs text-muted-foreground">Información adicional (reglas, equipamiento, etc.)</p>
                    <Textarea
                        id="notes"
                        v-model="form.notes"
                        placeholder="Información útil para los jugadores..."
                        rows="3"
                    />
                    <InputError :message="form.errors.notes" />
                </div>

                <Separator />

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

                <!-- Actions -->
                <div class="flex items-center gap-3 pt-2">
                    <Button type="submit" class="flex-1 gap-2" :disabled="form.processing">
                        <Plus class="size-4" />
                        Crear partido
                    </Button>
                    <Button variant="outline" as-child>
                        <Link :href="`/clubs/${club.ulid}/matches`">
                            Cancelar
                        </Link>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
