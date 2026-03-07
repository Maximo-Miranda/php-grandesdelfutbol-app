<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { MapPin, Pencil, Plus, Trash2, WandSparkles } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useClubPermissions } from '@/composables/useClubPermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, Field, Venue } from '@/types';

type Props = { club: Club; venue: Venue };
const props = defineProps<Props>();
const { isAdmin } = useClubPermissions();

const breadcrumbs: BreadcrumbItem[] = [
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
    { title: 'Canchas', href: `/clubs/${props.club.ulid}/venues` },
    { title: props.venue.name, href: `/clubs/${props.club.ulid}/venues/${props.venue.ulid}` },
];

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

// --- Add field ---
const fieldForm = useForm({ name: '', field_type: '5v5', surface_type: 'sintetico' });
const showAddField = ref(false);
const autoName = ref(true);

const nextFieldNumber = computed(() => (props.venue.fields?.length ?? 0) + 1);
const surfaceLabel = computed(() => surfaceOptions.find((o) => o.value === fieldForm.surface_type)?.label ?? fieldForm.surface_type);
const generatedName = computed(() => `Cancha ${nextFieldNumber.value} ${fieldForm.field_type} ${surfaceLabel.value}`);

fieldForm.name = generatedName.value;

watch(generatedName, (val) => {
    if (autoName.value) fieldForm.name = val;
});

function enableManualName() {
    autoName.value = false;
}

function enableAutoName() {
    autoName.value = true;
    fieldForm.name = generatedName.value;
}

function openAddField() {
    showAddField.value = true;
    editingField.value = null;
    autoName.value = true;
    fieldForm.reset();
    fieldForm.field_type = '5v5';
    fieldForm.surface_type = 'sintetico';
    fieldForm.name = generatedName.value;
}

function addField() {
    fieldForm.post(`/clubs/${props.club.ulid}/venues/${props.venue.ulid}/fields`, {
        preserveScroll: true,
        onSuccess: () => {
            fieldForm.reset();
            showAddField.value = false;
        },
    });
}

// --- Edit field ---
const editingField = ref<Field | null>(null);
const editForm = useForm({ name: '', field_type: '5v5', surface_type: '', is_active: true });

function startEdit(field: Field) {
    editingField.value = field;
    showAddField.value = false;
    editForm.name = field.name;
    editForm.field_type = field.field_type;
    editForm.surface_type = field.surface_type ?? '';
    editForm.is_active = field.is_active;
}

function cancelEdit() {
    editingField.value = null;
}

function saveEdit() {
    if (!editingField.value) return;
    editForm.put(`/clubs/${props.club.ulid}/venues/${props.venue.ulid}/fields/${editingField.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingField.value = null;
        },
    });
}

// --- Delete field ---
function deleteField(field: Field) {
    if (!confirm(`Eliminar "${field.name}"? Esta accion no se puede deshacer.`)) return;
    router.delete(`/clubs/${props.club.ulid}/venues/${props.venue.ulid}/fields/${field.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="venue.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <!-- Header -->
            <div class="mb-6 flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ venue.name }}</h1>
                    <p v-if="venue.address" class="mt-1 flex items-center gap-1.5 text-sm text-muted-foreground">
                        <MapPin class="size-3.5" />
                        {{ venue.address }}
                    </p>
                </div>
                <TextLink v-if="isAdmin" :href="`/clubs/${club.ulid}/venues/${venue.ulid}/edit`">Editar</TextLink>
            </div>

            <!-- Details -->
            <div class="mb-6 space-y-3 rounded-lg border border-border p-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium">Estado</span>
                    <Badge :variant="venue.is_active ? 'default' : 'secondary'">{{ venue.is_active ? 'Activo' : 'Inactivo' }}</Badge>
                </div>
                <div v-if="venue.map_link" class="flex items-center justify-between">
                    <span class="text-sm font-medium">Mapa</span>
                    <a :href="venue.map_link" target="_blank" class="text-sm text-primary underline">Ver en mapa</a>
                </div>
                <div v-if="venue.notes">
                    <span class="text-sm font-medium">Notas</span>
                    <p class="mt-1 text-sm text-muted-foreground">{{ venue.notes }}</p>
                </div>
            </div>

            <!-- Fields -->
            <div class="rounded-lg border border-border p-4">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="font-semibold">Canchas</h3>
                    <Button v-if="isAdmin && !showAddField" variant="outline" size="sm" @click="openAddField">
                        <Plus class="mr-1.5 size-4" />
                        Agregar
                    </Button>
                </div>

                <!-- Existing fields -->
                <div v-if="venue.fields && venue.fields.length > 0" class="space-y-2">
                    <div v-for="field in venue.fields" :key="field.id">
                        <!-- Edit mode -->
                        <form
                            v-if="editingField?.id === field.id"
                            class="space-y-3 rounded-lg border border-dashed border-primary/30 bg-primary/5 p-4"
                            @submit.prevent="saveEdit"
                        >
                            <p class="text-sm font-semibold">Editar cancha</p>
                            <div class="grid gap-1.5">
                                <Label>Nombre</Label>
                                <Input v-model="editForm.name" required />
                                <InputError :message="editForm.errors.name" />
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="grid gap-1.5">
                                    <Label>Tipo</Label>
                                    <Select v-model="editForm.field_type">
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="opt in fieldTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="editForm.errors.field_type" />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Superficie</Label>
                                    <Select v-model="editForm.surface_type">
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="opt in surfaceOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="editForm.errors.surface_type" />
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Checkbox id="edit_is_active" v-model="editForm.is_active" />
                                <Label for="edit_is_active">Activa</Label>
                            </div>
                            <div class="flex gap-2">
                                <Button type="submit" size="sm" :disabled="editForm.processing">Guardar</Button>
                                <Button type="button" variant="ghost" size="sm" @click="cancelEdit">Cancelar</Button>
                            </div>
                        </form>

                        <!-- View mode -->
                        <div v-else class="flex items-center justify-between rounded-md border border-border p-3">
                            <div>
                                <p class="font-medium">{{ field.name }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ field.field_type }}
                                    <span v-if="field.surface_type"> &middot; {{ field.surface_type }}</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge v-if="!field.is_active" variant="secondary">Inactiva</Badge>
                                <template v-if="isAdmin">
                                    <button type="button" class="p-1.5 text-muted-foreground hover:text-foreground" @click="startEdit(field)">
                                        <Pencil class="size-4" />
                                    </button>
                                    <button type="button" class="p-1.5 text-muted-foreground hover:text-destructive" @click="deleteField(field)">
                                        <Trash2 class="size-4" />
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else-if="!showAddField" class="rounded-lg border border-dashed p-6 text-center">
                    <p class="mb-2 text-sm text-muted-foreground">No hay canchas agregadas.</p>
                    <Button v-if="isAdmin" variant="outline" size="sm" @click="openAddField">
                        <Plus class="mr-1.5 size-4" />
                        Agregar primera cancha
                    </Button>
                </div>

                <!-- Add field form -->
                <form v-if="isAdmin && showAddField" class="mt-4 space-y-4 rounded-lg border border-dashed border-primary/30 bg-primary/5 p-4" @submit.prevent="addField">
                    <p class="text-sm font-semibold">Nueva cancha</p>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5">
                            <Label for="field_type">Tipo</Label>
                            <Select v-model="fieldForm.field_type">
                                <SelectTrigger id="field_type"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="opt in fieldTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="fieldForm.errors.field_type" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="surface_type">Superficie</Label>
                            <Select v-model="fieldForm.surface_type">
                                <SelectTrigger id="surface_type"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="opt in surfaceOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="fieldForm.errors.surface_type" />
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="field_name">Nombre</Label>
                        <div class="relative">
                            <Input
                                id="field_name"
                                v-model="fieldForm.name"
                                required
                                class="pr-9"
                                :readonly="autoName"
                                :class="autoName ? 'bg-muted/50' : ''"
                            />
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground hover:text-foreground"
                                @click="autoName ? enableManualName() : enableAutoName()"
                            >
                                <Pencil v-if="autoName" class="size-4" />
                                <WandSparkles v-else class="size-4" />
                            </button>
                        </div>
                        <p v-if="autoName" class="text-xs text-muted-foreground">
                            Se genera automaticamente. Presiona el lapiz para editar.
                        </p>
                        <InputError :message="fieldForm.errors.name" />
                    </div>

                    <div class="flex gap-2">
                        <Button type="submit" size="sm" :disabled="fieldForm.processing">Agregar cancha</Button>
                        <Button type="button" variant="ghost" size="sm" @click="showAddField = false">Cancelar</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
