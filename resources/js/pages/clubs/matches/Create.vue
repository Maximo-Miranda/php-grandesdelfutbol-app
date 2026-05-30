<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Bell,
    CalendarClock,
    Check,
    Clock,
    Handshake,
    Info,
    MapPin,
    Pencil,
    Plus,
    Repeat,
    ShieldX,
    Trophy,
    User,
    WandSparkles,
} from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import ColorSwatchPicker from '@/components/ColorSwatchPicker.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import {
    formatShortDateTime,
    recurrenceOptions,
    timeOptions,
    useMatchForm,
} from '@/composables/useMatchForm';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, Venue } from '@/types';

type TeamOption = {
    id: number;
    ulid: string;
    name: string;
    color: string;
    logo_url: string | null;
};
type Props = {
    club: Club;
    venues: Venue[];
    defaultCancelHoursBefore: number;
    availableTeams?: TeamOption[];
};
const props = withDefaults(defineProps<Props>(), { availableTeams: () => [] });

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
    autoRegistration,
    selectedFieldLabel,
    selectedDate,
    selectedTime,
    registrationDate,
    registrationTime,
    registrationClosesDate,
    registrationClosesTime,
    generatedRegistrationOpensAt,
    enableManualTitle,
    enableAutoTitle,
    enableManualTeamName,
    enableAutoTeamName,
    enableManualRegistration,
    enableAutoRegistration,
    isPastMatch,
    selectedRecurrenceOption,
    resolveBeforeSubmit,
} = useMatchForm({
    venues: () => props.venues,
    defaultCancelHoursBefore: props.defaultCancelHoursBefore,
});

function onTeamAChange(value: string): void {
    const id = value === 'none' ? null : Number(value);
    form.team_a_id = id;
    const team = id ? props.availableTeams.find((t) => t.id === id) : null;
    if (team) {
        form.team_a_name = team.name;
        form.team_a_color = team.color;
    }
}

function onTeamBChange(value: string): void {
    const id = value === 'none' ? null : Number(value);
    form.team_b_id = id;
    const team = id ? props.availableTeams.find((t) => t.id === id) : null;
    if (team) {
        form.team_b_name = team.name;
        form.team_b_color = team.color;
    }
}

function toggleSingleTeam(): void {
    form.single_team = !form.single_team;
    if (form.single_team) {
        form.team_b_id = null;
    }
}

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
    nextTick(() => {
        venueForm.field_name = generatedFieldName.value;
        fieldForm.name = generatedFieldName.value;
    });
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

const activeSurfaceType = computed(() =>
    dialogMode.value === 'new'
        ? venueForm.surface_type
        : fieldForm.surface_type,
);
const activeFieldType = computed(() =>
    dialogMode.value === 'new' ? venueForm.field_type : fieldForm.field_type,
);
const surfaceLabel = computed(
    () =>
        surfaceOptions.find((o) => o.value === activeSurfaceType.value)
            ?.label ?? activeSurfaceType.value,
);

const selectedVenueFieldCount = computed(() => {
    const venue = props.venues.find((v) => v.ulid === selectedVenueUlid.value);
    return (venue?.fields?.length ?? 0) + 1;
});

const generatedFieldName = computed(() => {
    const num =
        dialogMode.value === 'existing' ? selectedVenueFieldCount.value : 1;
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
    get: () =>
        dialogMode.value === 'new'
            ? venueForm.field_type
            : fieldForm.field_type,
    set: (v: string) => {
        if (dialogMode.value === 'new') venueForm.field_type = v;
        else fieldForm.field_type = v;
    },
});

const activeSurfaceTypeModel = computed({
    get: () =>
        dialogMode.value === 'new'
            ? venueForm.surface_type
            : fieldForm.surface_type,
    set: (v: string) => {
        if (dialogMode.value === 'new') venueForm.surface_type = v;
        else fieldForm.surface_type = v;
    },
});

const activeFieldNameModel = computed({
    get: () =>
        dialogMode.value === 'new' ? venueForm.field_name : fieldForm.name,
    set: (v: string) => {
        if (dialogMode.value === 'new') venueForm.field_name = v;
        else fieldForm.name = v;
    },
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
        onSuccess: () => {
            showVenueDialog.value = false;
        },
        onError: () => {
            pendingAutoSelect.value = false;
        },
    });
}

function submitExistingVenueField() {
    const venue = props.venues.find((v) => v.ulid === selectedVenueUlid.value);
    if (!venue) return;
    pendingAutoSelect.value = true;
    fieldForm.post(`/clubs/${props.club.ulid}/venues/${venue.ulid}/fields`, {
        preserveScroll: true,
        onSuccess: () => {
            showVenueDialog.value = false;
        },
        onError: () => {
            pendingAutoSelect.value = false;
        },
    });
}
</script>

<template>
    <Head title="Crear partido" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <h1 class="text-2xl font-bold">Crear Partido</h1>
            <p class="mb-6 text-sm text-muted-foreground">
                Programa un partido y comparte el link para confirmar
                asistencia.
            </p>

            <form class="space-y-5" @submit.prevent="submit">
                <!-- Cancha -->
                <div class="grid gap-1.5">
                    <Label for="field_id">Cancha</Label>
                    <p class="text-xs text-muted-foreground">
                        Selecciona la cancha donde se jugara.
                    </p>
                    <Select v-model="selectedFieldLabel">
                        <SelectTrigger
                            id="field_id"
                            class="min-w-0 overflow-hidden"
                        >
                            <MapPin
                                class="size-4 shrink-0 text-muted-foreground"
                            />
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
                        <button
                            type="button"
                            class="ml-auto flex items-center gap-1 text-xs text-primary hover:underline"
                            @click="openDialog"
                        >
                            <Plus class="size-3" />
                            Agregar nueva cancha
                        </button>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Nueva cancha</DialogTitle>
                                <DialogDescription
                                    >Agrega una cancha para poder seleccionarla
                                    en el partido.</DialogDescription
                                >
                            </DialogHeader>

                            <!-- Mode toggle -->
                            <div
                                v-if="hasVenues"
                                class="flex gap-1 rounded-lg bg-muted p-1"
                            >
                                <button
                                    type="button"
                                    class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                                    :class="
                                        dialogMode === 'existing'
                                            ? 'bg-background shadow-sm'
                                            : 'text-muted-foreground hover:text-foreground'
                                    "
                                    @click="dialogMode = 'existing'"
                                >
                                    Lugar existente
                                </button>
                                <button
                                    type="button"
                                    class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                                    :class="
                                        dialogMode === 'new'
                                            ? 'bg-background shadow-sm'
                                            : 'text-muted-foreground hover:text-foreground'
                                    "
                                    @click="dialogMode = 'new'"
                                >
                                    Nuevo lugar
                                </button>
                            </div>

                            <div class="space-y-4">
                                <!-- Existing venue: select -->
                                <div
                                    v-if="dialogMode === 'existing'"
                                    class="grid gap-1.5"
                                >
                                    <Label for="existing_venue"
                                        >Lugar
                                        <span class="text-destructive"
                                            >*</span
                                        ></Label
                                    >
                                    <Select v-model="selectedVenueUlid">
                                        <SelectTrigger id="existing_venue">
                                            <MapPin
                                                class="size-4 shrink-0 text-muted-foreground"
                                            />
                                            <SelectValue
                                                placeholder="Selecciona un lugar"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="v in venues"
                                                :key="v.ulid"
                                                :value="v.ulid"
                                            >
                                                {{ v.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <!-- New venue: name + address -->
                                <template v-if="dialogMode === 'new'">
                                    <div class="grid gap-1.5">
                                        <Label for="venue_name"
                                            >Nombre del lugar
                                            <span class="text-destructive"
                                                >*</span
                                            ></Label
                                        >
                                        <div class="relative">
                                            <div
                                                class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"
                                            >
                                                <MapPin
                                                    class="size-4 text-muted-foreground"
                                                />
                                            </div>
                                            <Input
                                                id="venue_name"
                                                v-model="venueForm.name"
                                                required
                                                placeholder="ej. Cancha Brazileirao"
                                                class="pl-9"
                                            />
                                        </div>
                                        <InputError
                                            :message="venueForm.errors.name"
                                        />
                                    </div>

                                    <div class="grid gap-1.5">
                                        <Label for="venue_address"
                                            >Dirección</Label
                                        >
                                        <Input
                                            id="venue_address"
                                            v-model="venueForm.address"
                                            placeholder="ej. Calle 4a # 13-39"
                                        />
                                        <InputError
                                            :message="venueForm.errors.address"
                                        />
                                    </div>

                                    <div class="grid gap-1.5">
                                        <Label for="venue_map_link"
                                            >Enlace de Google Maps</Label
                                        >
                                        <Input
                                            id="venue_map_link"
                                            v-model="venueForm.map_link"
                                            placeholder="https://maps.google.com/..."
                                        />
                                        <InputError
                                            :message="venueForm.errors.map_link"
                                        />
                                    </div>
                                </template>

                                <Separator />

                                <!-- Field details (shared) -->
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="grid gap-1.5">
                                        <Label for="venue_field_type"
                                            >Tipo de cancha
                                            <span class="text-destructive"
                                                >*</span
                                            ></Label
                                        >
                                        <Select v-model="activeFieldTypeModel">
                                            <SelectTrigger id="venue_field_type"
                                                ><SelectValue
                                            /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="opt in fieldTypeOptions"
                                                    :key="opt.value"
                                                    :value="opt.value"
                                                    >{{ opt.label }}</SelectItem
                                                >
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="venue_surface_type"
                                            >Superficie</Label
                                        >
                                        <Select
                                            v-model="activeSurfaceTypeModel"
                                        >
                                            <SelectTrigger
                                                id="venue_surface_type"
                                                ><SelectValue
                                            /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="opt in surfaceOptions"
                                                    :key="opt.value"
                                                    :value="opt.value"
                                                    >{{ opt.label }}</SelectItem
                                                >
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div class="grid gap-1.5">
                                    <Label for="venue_field_name"
                                        >Nombre de la cancha
                                        <span class="text-destructive"
                                            >*</span
                                        ></Label
                                    >
                                    <div class="relative">
                                        <Input
                                            id="venue_field_name"
                                            v-model="activeFieldNameModel"
                                            required
                                            class="pr-9"
                                            :readonly="autoFieldName"
                                            :class="
                                                autoFieldName
                                                    ? 'bg-muted/50'
                                                    : ''
                                            "
                                        />
                                        <button
                                            type="button"
                                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground hover:text-foreground"
                                            @click="
                                                autoFieldName = !autoFieldName;
                                                if (autoFieldName)
                                                    activeFieldNameModel =
                                                        generatedFieldName;
                                            "
                                        >
                                            <Pencil
                                                v-if="autoFieldName"
                                                class="size-4"
                                            />
                                            <WandSparkles
                                                v-else
                                                class="size-4"
                                            />
                                        </button>
                                    </div>
                                    <p
                                        v-if="autoFieldName"
                                        class="text-xs text-muted-foreground"
                                    >
                                        Se genera automaticamente.
                                    </p>
                                </div>
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    :disabled="
                                        dialogMode === 'new'
                                            ? venueForm.processing
                                            : fieldForm.processing ||
                                              !selectedVenueUlid
                                    "
                                    @click="
                                        dialogMode === 'new'
                                            ? submitNewVenue()
                                            : submitExistingVenueField()
                                    "
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
                        <Label for="date"
                            >Fecha
                            <span class="text-destructive">*</span></Label
                        >
                        <Input id="date" v-model="selectedDate" type="date" />
                        <InputError :message="form.errors.scheduled_at" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="time"
                            >Hora <span class="text-destructive">*</span></Label
                        >
                        <Select v-model="selectedTime">
                            <SelectTrigger id="time">
                                <div class="flex items-center gap-2">
                                    <Clock
                                        class="size-4 text-muted-foreground"
                                    />
                                    <SelectValue placeholder="Seleccionar" />
                                </div>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="t in timeOptions"
                                    :key="t"
                                    :value="t"
                                >
                                    {{ t }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <!-- Past match alert -->
                <div
                    v-if="isPastMatch"
                    class="flex items-start gap-2 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-200"
                >
                    <Info class="mt-0.5 size-4 shrink-0" />
                    <span
                        >Este partido se creará como finalizado. Podrás
                        confirmar jugadores y cargar estadísticas
                        manualmente.</span
                    >
                </div>

                <!-- Duracion y Llegada -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label for="duration_minutes"
                            >Duracion (min)
                            <span class="text-destructive">*</span></Label
                        >
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
                        <Label for="max_players"
                            >Max jugadores
                            <span class="text-destructive">*</span></Label
                        >
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

                <!-- Apertura de convocatoria -->
                <div class="grid gap-1.5">
                    <Label
                        >Apertura de convocatoria
                        <span class="text-destructive">*</span></Label
                    >

                    <!-- Modo auto -->
                    <template v-if="autoRegistration">
                        <p class="text-xs text-muted-foreground">
                            Se calcula automáticamente. Presiona el lápiz para
                            establecer fecha y hora exacta.
                        </p>
                        <div class="relative">
                            <div
                                class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"
                            >
                                <Bell class="size-4 text-muted-foreground" />
                            </div>
                            <Input
                                id="registration_opens_hours"
                                v-model="form.registration_opens_hours"
                                type="number"
                                class="bg-muted/50 pr-9 pl-9"
                                readonly
                            />
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground hover:text-foreground"
                                @click="enableManualRegistration()"
                            >
                                <Pencil class="size-4" />
                            </button>
                        </div>
                        <p
                            v-if="generatedRegistrationOpensAt"
                            class="text-xs text-muted-foreground"
                        >
                            Convocatoria se abre:
                            <span class="font-medium">{{
                                formatShortDateTime(
                                    generatedRegistrationOpensAt,
                                )
                            }}</span>
                        </p>
                    </template>

                    <!-- Modo manual -->
                    <template v-else>
                        <p class="text-xs text-muted-foreground">
                            Fecha y hora exacta en que se abre la convocatoria.
                        </p>
                        <div
                            class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto_auto]"
                        >
                            <div class="relative">
                                <div
                                    class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"
                                >
                                    <CalendarClock
                                        class="size-4 text-muted-foreground"
                                    />
                                </div>
                                <Input
                                    id="registration_date"
                                    v-model="registrationDate"
                                    type="date"
                                    class="pl-9"
                                />
                            </div>
                            <Select v-model="registrationTime">
                                <SelectTrigger>
                                    <div class="flex items-center gap-2">
                                        <Clock
                                            class="size-4 text-muted-foreground"
                                        />
                                        <SelectValue placeholder="Hora" />
                                    </div>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="t in timeOptions"
                                        :key="t"
                                        :value="t"
                                        >{{ t }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <button
                                type="button"
                                class="flex items-center justify-center rounded-md border border-input px-3 text-muted-foreground hover:text-foreground"
                                @click="enableAutoRegistration()"
                            >
                                <WandSparkles class="size-4" />
                            </button>
                        </div>
                    </template>

                    <InputError
                        :message="form.errors.registration_opens_hours"
                    />
                    <InputError :message="form.errors.registration_opens_at" />
                </div>

                <!-- Cierre de convocatoria -->
                <div class="grid gap-1.5">
                    <Label>Cierre de convocatoria</Label>
                    <p class="text-xs text-muted-foreground">
                        Opcional. Si lo dejas vacío, la convocatoria cierra al
                        inicio del partido.
                    </p>
                    <div
                        class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto]"
                    >
                        <div class="relative">
                            <div
                                class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"
                            >
                                <CalendarClock
                                    class="size-4 text-muted-foreground"
                                />
                            </div>
                            <Input
                                id="registration_closes_date"
                                v-model="registrationClosesDate"
                                type="date"
                                class="pl-9"
                            />
                        </div>
                        <Select v-model="registrationClosesTime">
                            <SelectTrigger>
                                <div class="flex items-center gap-2">
                                    <Clock
                                        class="size-4 text-muted-foreground"
                                    />
                                    <SelectValue placeholder="Hora" />
                                </div>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="t in timeOptions"
                                    :key="t"
                                    :value="t"
                                    >{{ t }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <InputError
                        :message="form.errors.registration_closes_at"
                    />
                </div>

                <!-- Equipos -->
                <div class="grid gap-3">
                    <div>
                        <Label>Equipos</Label>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Selecciona equipos existentes de la temporada o
                            define nombres manualmente. Los equipos
                            seleccionados aportan a la tabla de posiciones.
                        </p>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2">
                        <button
                            type="button"
                            class="group relative flex items-start gap-3 rounded-lg border p-3 text-left transition active:scale-[0.99]"
                            :class="
                                form.single_team
                                    ? 'border-primary bg-primary/5 ring-1 ring-primary/20'
                                    : 'border-border hover:border-muted-foreground/40'
                            "
                            @click="toggleSingleTeam()"
                        >
                            <span
                                class="flex size-8 shrink-0 items-center justify-center rounded-md border"
                                :class="
                                    form.single_team
                                        ? 'border-primary/40 bg-primary/10 text-primary'
                                        : 'border-border bg-muted text-muted-foreground'
                                "
                            >
                                <User class="size-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span
                                    class="flex items-center justify-between gap-2"
                                >
                                    <span class="text-sm font-medium"
                                        >Un solo equipo</span
                                    >
                                    <span
                                        class="flex size-4 shrink-0 items-center justify-center rounded-full border transition"
                                        :class="
                                            form.single_team
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-muted-foreground/30 bg-background'
                                        "
                                    >
                                        <Check
                                            v-if="form.single_team"
                                            class="size-3"
                                        />
                                    </span>
                                </span>
                                <span
                                    class="mt-0.5 block text-xs text-muted-foreground"
                                >
                                    Para clubes donde los miembros juegan contra
                                    un rival externo.
                                </span>
                            </span>
                        </button>

                        <button
                            type="button"
                            class="group relative flex items-start gap-3 rounded-lg border p-3 text-left transition active:scale-[0.99]"
                            :class="
                                form.is_friendly
                                    ? 'border-amber-500 bg-amber-500/5 ring-1 ring-amber-500/20'
                                    : 'border-border hover:border-muted-foreground/40'
                            "
                            @click="form.is_friendly = !form.is_friendly"
                        >
                            <span
                                class="flex size-8 shrink-0 items-center justify-center rounded-md border"
                                :class="
                                    form.is_friendly
                                        ? 'border-amber-500/40 bg-amber-500/10 text-amber-600'
                                        : 'border-border bg-muted text-muted-foreground'
                                "
                            >
                                <Handshake class="size-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span
                                    class="flex items-center justify-between gap-2"
                                >
                                    <span class="text-sm font-medium"
                                        >Amistoso</span
                                    >
                                    <span
                                        class="flex size-4 shrink-0 items-center justify-center rounded-full border transition"
                                        :class="
                                            form.is_friendly
                                                ? 'border-amber-500 bg-amber-500 text-white'
                                                : 'border-muted-foreground/30 bg-background'
                                        "
                                    >
                                        <Check
                                            v-if="form.is_friendly"
                                            class="size-3"
                                        />
                                    </span>
                                </span>
                                <span
                                    class="mt-0.5 block text-xs text-muted-foreground"
                                >
                                    No suma en la tabla de posiciones, pero se
                                    muestra en Últimos 5.
                                </span>
                            </span>
                        </button>
                    </div>

                    <div
                        v-if="availableTeams.length > 0"
                        class="mt-2 grid gap-4"
                        :class="
                            form.single_team
                                ? 'sm:grid-cols-1'
                                : 'sm:grid-cols-2'
                        "
                    >
                        <div class="grid gap-2">
                            <Label class="text-xs text-muted-foreground"
                                >Equipo A</Label
                            >
                            <Select
                                :model-value="
                                    form.team_a_id
                                        ? String(form.team_a_id)
                                        : 'none'
                                "
                                @update:model-value="onTeamAChange"
                            >
                                <SelectTrigger dusk="match-team-a-trigger">
                                    <SelectValue
                                        placeholder="Selecciona equipo A"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none"
                                        >— Usar nombre manual —</SelectItem
                                    >
                                    <SelectItem
                                        v-for="t in availableTeams"
                                        :key="t.id"
                                        :value="String(t.id)"
                                        >{{ t.name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                        <div v-if="!form.single_team" class="grid gap-2">
                            <Label class="text-xs text-muted-foreground"
                                >Equipo B</Label
                            >
                            <Select
                                :model-value="
                                    form.team_b_id
                                        ? String(form.team_b_id)
                                        : 'none'
                                "
                                @update:model-value="onTeamBChange"
                            >
                                <SelectTrigger dusk="match-team-b-trigger">
                                    <SelectValue
                                        placeholder="Selecciona equipo B"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none"
                                        >— Usar nombre manual —</SelectItem
                                    >
                                    <SelectItem
                                        v-for="t in availableTeams"
                                        :key="t.id"
                                        :value="String(t.id)"
                                        >{{ t.name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div
                        class="mt-2 grid gap-4"
                        :class="
                            form.single_team
                                ? 'sm:grid-cols-1'
                                : 'sm:grid-cols-2'
                        "
                    >
                        <div
                            class="grid gap-2 rounded-md border border-border p-3"
                        >
                            <div class="relative">
                                <Input
                                    id="team_a_name"
                                    v-model="form.team_a_name"
                                    class="pr-9"
                                    :readonly="autoTeamA || !!form.team_a_id"
                                    :class="
                                        autoTeamA || form.team_a_id
                                            ? 'bg-muted/50'
                                            : ''
                                    "
                                />
                                <button
                                    v-if="!form.team_a_id"
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground hover:text-foreground"
                                    @click="
                                        autoTeamA
                                            ? enableManualTeamName('a')
                                            : enableAutoTeamName('a')
                                    "
                                >
                                    <Pencil v-if="autoTeamA" class="size-4" />
                                    <WandSparkles v-else class="size-4" />
                                </button>
                            </div>
                            <ColorSwatchPicker v-model="form.team_a_color" />
                        </div>
                        <div
                            v-if="!form.single_team"
                            class="grid gap-2 rounded-md border border-border p-3"
                        >
                            <div class="relative">
                                <Input
                                    id="team_b_name"
                                    v-model="form.team_b_name"
                                    class="pr-9"
                                    :readonly="autoTeamB || !!form.team_b_id"
                                    :class="
                                        autoTeamB || form.team_b_id
                                            ? 'bg-muted/50'
                                            : ''
                                    "
                                />
                                <button
                                    v-if="!form.team_b_id"
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground hover:text-foreground"
                                    @click="
                                        autoTeamB
                                            ? enableManualTeamName('b')
                                            : enableAutoTeamName('b')
                                    "
                                >
                                    <Pencil v-if="autoTeamB" class="size-4" />
                                    <WandSparkles v-else class="size-4" />
                                </button>
                            </div>
                            <ColorSwatchPicker v-model="form.team_b_color" />
                        </div>
                    </div>
                </div>

                <!-- Permitir jugadores fuera de la nómina -->
                <div
                    v-if="form.team_a_id || form.team_b_id"
                    class="grid gap-1.5"
                >
                    <Label>Jugadores fuera de la nómina</Label>
                    <p class="text-xs text-muted-foreground">
                        Permite que jugadores que no están en la nómina de los
                        equipos seleccionados confirmen asistencia. Quedan en un
                        pool y se distribuyen al sortear.
                    </p>
                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="allow_outsiders"
                            v-model="form.allow_outsiders"
                        />
                        <Label for="allow_outsiders" class="font-normal"
                            >Permitir jugadores fuera de la nómina</Label
                        >
                    </div>
                    <InputError :message="form.errors.allow_outsiders" />
                </div>

                <!-- Notas -->
                <div class="grid gap-1.5">
                    <Label for="notes">Notas</Label>
                    <p class="text-xs text-muted-foreground">
                        Información adicional (reglas, equipamiento, etc.)
                    </p>
                    <Textarea
                        id="notes"
                        v-model="form.notes"
                        placeholder="Información útil para los jugadores..."
                        rows="3"
                    />
                    <InputError :message="form.errors.notes" />
                </div>

                <!-- Recurrencia -->
                <div class="grid gap-1.5">
                    <Label>Recurrencia</Label>
                    <p class="text-xs text-muted-foreground">
                        Cuando el partido finalice, se creará automáticamente el
                        siguiente.
                    </p>
                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="is_recurring"
                            v-model="form.is_recurring"
                        />
                        <Label for="is_recurring" class="font-normal"
                            >Auto-recrear partido</Label
                        >
                    </div>
                    <div v-if="form.is_recurring" class="mt-2 grid gap-1.5">
                        <Label>Frecuencia</Label>
                        <Select v-model="selectedRecurrenceOption">
                            <SelectTrigger class="max-w-[250px]">
                                <div class="flex items-center gap-2">
                                    <Repeat
                                        class="size-4 text-muted-foreground"
                                    />
                                    <SelectValue />
                                </div>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="opt in recurrenceOptions"
                                    :key="opt.value"
                                    :value="opt.value"
                                    >{{ opt.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <div
                            v-if="selectedRecurrenceOption === 'custom'"
                            class="max-w-[200px]"
                        >
                            <Input
                                id="recurrence_days"
                                v-model="form.recurrence_days"
                                type="number"
                                min="1"
                                max="90"
                                placeholder="Días"
                            />
                        </div>
                        <InputError :message="form.errors.recurrence_days" />
                    </div>
                </div>

                <!-- Auto-cancelar -->
                <div class="grid gap-1.5">
                    <Label>Auto-cancelar</Label>
                    <p class="text-xs text-muted-foreground">
                        Si no hay suficientes jugadores antes del partido, se
                        cancela automáticamente.
                    </p>
                    <div class="flex items-center gap-2">
                        <Checkbox id="auto_cancel" v-model="form.auto_cancel" />
                        <Label for="auto_cancel" class="font-normal"
                            >Auto-cancelar por falta de jugadores</Label
                        >
                    </div>
                    <div v-if="form.auto_cancel" class="mt-2 grid gap-3">
                        <div class="grid gap-1.5">
                            <Label for="min_players_required"
                                >Mínimo de jugadores</Label
                            >
                            <div class="relative max-w-[200px]">
                                <div
                                    class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"
                                >
                                    <ShieldX
                                        class="size-4 text-muted-foreground"
                                    />
                                </div>
                                <Input
                                    id="min_players_required"
                                    v-model="form.min_players_required"
                                    type="number"
                                    min="2"
                                    :max="form.max_players"
                                    class="pl-9"
                                />
                            </div>
                            <InputError
                                :message="form.errors.min_players_required"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="cancel_hours_before"
                                >Horas antes para cancelar</Label
                            >
                            <p class="text-xs text-muted-foreground">
                                Horas antes del partido para verificar y
                                auto-cancelar.
                            </p>
                            <div class="relative max-w-[200px]">
                                <div
                                    class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"
                                >
                                    <Clock
                                        class="size-4 text-muted-foreground"
                                    />
                                </div>
                                <Input
                                    id="cancel_hours_before"
                                    v-model="form.cancel_hours_before"
                                    type="number"
                                    min="1"
                                    max="168"
                                    placeholder="10"
                                    class="pl-9"
                                />
                            </div>
                            <InputError
                                :message="form.errors.cancel_hours_before"
                            />
                        </div>
                    </div>
                </div>

                <Separator />

                <!-- Titulo -->
                <div class="grid gap-1.5">
                    <Label for="title"
                        >Titulo <span class="text-destructive">*</span></Label
                    >
                    <div class="relative">
                        <div
                            class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"
                        >
                            <Trophy class="size-4 text-muted-foreground" />
                        </div>
                        <Input
                            id="title"
                            v-model="form.title"
                            placeholder="Ej: Pichanga 7v7 Sab 28 Feb"
                            class="pr-9 pl-9"
                            :readonly="autoTitle"
                            :class="autoTitle ? 'bg-muted/50' : ''"
                        />
                        <button
                            type="button"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground hover:text-foreground"
                            @click="
                                autoTitle
                                    ? enableManualTitle()
                                    : enableAutoTitle()
                            "
                        >
                            <Pencil v-if="autoTitle" class="size-4" />
                            <WandSparkles v-else class="size-4" />
                        </button>
                    </div>
                    <p v-if="autoTitle" class="text-xs text-muted-foreground">
                        Se genera automaticamente. Presiona el lapiz para
                        editar.
                    </p>
                    <InputError :message="form.errors.title" />
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-3 pt-2">
                    <Button
                        type="submit"
                        class="flex-1 gap-2"
                        :disabled="form.processing"
                        dusk="match-create-submit"
                    >
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
