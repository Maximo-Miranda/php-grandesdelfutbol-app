<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Camera, ImagePlus, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ColorSwatchPicker from '@/components/ColorSwatchPicker.vue';
import PlayerPicker from '@/components/PlayerPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club } from '@/types';

type PlayerOption = { id: number; ulid: string; name: string; jersey_number: number | null; position: string | null };

const props = defineProps<{
    club: Club;
    activeSeason: { ulid: string; name: string };
    players: PlayerOption[];
}>();

const logoInput = ref<HTMLInputElement | null>(null);
const coverInput = ref<HTMLInputElement | null>(null);
const logoPreview = ref<string | null>(null);
const coverPreview = ref<string | null>(null);
const logoError = ref<string | null>(null);
const coverError = ref<string | null>(null);

const MAX_LOGO_BYTES = 5 * 1024 * 1024;
const MAX_COVER_BYTES = 10 * 1024 * 1024;
const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

function validateImage(file: File, maxBytes: number, maxMb: number): string | null {
    if (!ALLOWED_MIMES.includes(file.type)) {
        return 'Formato no soportado. Usa JPG, PNG o WebP.';
    }
    if (file.size > maxBytes) {
        const sizeMb = (file.size / (1024 * 1024)).toFixed(1);
        return `La imagen pesa ${sizeMb} MB. Máximo permitido: ${maxMb} MB.`;
    }
    return null;
}

const form = useForm({
    name: '',
    color: '#dc2626',
    coach_player_id: null as number | null,
    captain_player_id: null as number | null,
    bio: '',
    is_tournament: false,
    logo: null as File | null,
    cover: null as File | null,
});

const initial = computed(() => form.name?.charAt(0).toUpperCase() || '?');

function handleLogo(e: Event): void {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    logoError.value = null;

    if (!file) {
        form.logo = null;
        logoPreview.value = null;
        return;
    }

    const error = validateImage(file, MAX_LOGO_BYTES, 5);
    if (error) {
        logoError.value = error;
        input.value = '';
        return;
    }

    form.logo = file;
    logoPreview.value = URL.createObjectURL(file);
}

function handleCover(e: Event): void {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    coverError.value = null;

    if (!file) {
        form.cover = null;
        coverPreview.value = null;
        return;
    }

    const error = validateImage(file, MAX_COVER_BYTES, 10);
    if (error) {
        coverError.value = error;
        input.value = '';
        return;
    }

    form.cover = file;
    coverPreview.value = URL.createObjectURL(file);
}

function selectLogo(): void { logoInput.value?.click(); }
function selectCover(): void { coverInput.value?.click(); }
function removeLogo(): void {
    form.logo = null;
    logoPreview.value = null;
    logoError.value = null;
    if (logoInput.value) logoInput.value.value = '';
}
function removeCover(): void {
    form.cover = null;
    coverPreview.value = null;
    coverError.value = null;
    if (coverInput.value) coverInput.value.value = '';
}

function submit(): void {
    form.post(`/clubs/${props.club.ulid}/teams`, {
        forceFormData: true,
    });
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubes', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
    { title: 'Posiciones', href: `/clubs/${props.club.ulid}/standings` },
    { title: 'Equipos', href: `/clubs/${props.club.ulid}/teams` },
    { title: 'Crear', href: `/clubs/${props.club.ulid}/teams/create` },
];
</script>

<template>
    <Head :title="`${club.name} - Crear equipo`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <h1 class="mb-6 text-2xl font-bold">Crear equipo</h1>

            <p class="mb-6 text-sm text-muted-foreground">
                El equipo se asignará a la temporada activa: <span class="font-semibold text-foreground">{{ activeSeason.name }}</span>.
            </p>

            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium">Nombre</label>
                    <Input v-model="form.name" required maxlength="50" placeholder="Ej: Argentina" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-destructive">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Color</label>
                    <ColorSwatchPicker v-model="form.color" />
                    <p v-if="form.errors.color" class="mt-1 text-xs text-destructive">{{ form.errors.color }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Director Técnico (opcional)</label>
                        <PlayerPicker v-model="form.coach_player_id" :players="players" show-position />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Capitán (opcional)</label>
                        <PlayerPicker v-model="form.captain_player_id" :players="players" />
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Descripción (opcional)</label>
                    <Textarea v-model="form.bio" maxlength="1000" rows="3" placeholder="Historia, estilo, frase..." />
                </div>

                <label class="flex cursor-pointer items-start gap-3 rounded-md border border-border p-3 transition-colors hover:bg-accent/30">
                    <input
                        v-model="form.is_tournament"
                        type="checkbox"
                        class="mt-0.5 size-4 rounded border-border"
                    />
                    <div class="flex-1">
                        <p class="text-sm font-medium">Equipo de torneo</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Los jugadores pueden estar en varias plantillas de torneo dentro del club. Los equipos regulares (sin marcar) tienen plantilla exclusiva: un jugador solo puede estar en uno.
                        </p>
                    </div>
                </label>

                <!-- Imágenes -->
                <div class="grid gap-4 sm:grid-cols-[auto_1fr]">
                    <!-- Escudo (avatar redondo) -->
                    <div>
                        <label class="mb-2 block text-sm font-medium">Escudo</label>
                        <div class="relative size-24">
                            <button
                                type="button"
                                class="group relative flex size-full items-center justify-center overflow-hidden rounded-full border-2 border-dashed border-border bg-muted/40 text-2xl font-bold text-muted-foreground transition hover:border-primary/60 hover:bg-muted"
                                :style="!logoPreview ? { background: `linear-gradient(135deg, ${form.color}22, ${form.color}11)` } : {}"
                                @click="selectLogo"
                            >
                                <img v-if="logoPreview" :src="logoPreview" alt="Escudo" class="size-full object-cover">
                                <span v-else class="text-foreground/70">{{ initial }}</span>
                                <div class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 transition group-hover:opacity-100">
                                    <Camera class="size-5 text-white" />
                                </div>
                            </button>
                            <button
                                v-if="logoPreview"
                                type="button"
                                title="Quitar escudo"
                                aria-label="Quitar escudo"
                                class="absolute -right-1 -top-1 z-10 flex size-7 items-center justify-center rounded-full border border-border bg-background text-muted-foreground shadow-md transition hover:border-destructive hover:bg-destructive hover:text-destructive-foreground"
                                @click="removeLogo"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>
                        <p class="mt-1.5 text-[11px] text-muted-foreground">JPG, PNG o WebP · Max 5 MB</p>
                        <p v-if="logoError" class="mt-1 text-xs text-destructive">{{ logoError }}</p>
                        <p v-else-if="form.errors.logo" class="mt-1 text-xs text-destructive">{{ form.errors.logo }}</p>
                        <input
                            ref="logoInput"
                            type="file"
                            class="hidden"
                            accept="image/jpeg,image/png,image/webp"
                            @change="handleLogo"
                        >
                    </div>

                    <!-- Portada (banner panorámica) -->
                    <div>
                        <label class="mb-2 block text-sm font-medium">Portada</label>
                        <div class="relative h-24 w-full">
                            <button
                                type="button"
                                class="group relative flex size-full items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-border bg-muted/40 transition hover:border-primary/60 hover:bg-muted"
                                :style="!coverPreview ? { background: `linear-gradient(135deg, ${form.color}33, ${form.color}11)` } : {}"
                                @click="selectCover"
                            >
                                <img v-if="coverPreview" :src="coverPreview" alt="Portada" class="size-full object-cover">
                                <div v-else class="flex flex-col items-center gap-1 text-muted-foreground">
                                    <ImagePlus class="size-5" />
                                    <span class="text-[11px] font-medium">Subir portada</span>
                                </div>
                                <div class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 transition group-hover:opacity-100">
                                    <Camera class="size-5 text-white" />
                                </div>
                            </button>
                            <button
                                v-if="coverPreview"
                                type="button"
                                title="Quitar portada"
                                aria-label="Quitar portada"
                                class="absolute -right-1 -top-1 z-10 flex size-7 items-center justify-center rounded-full border border-border bg-background text-muted-foreground shadow-md transition hover:border-destructive hover:bg-destructive hover:text-destructive-foreground"
                                @click="removeCover"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>
                        <p class="mt-1.5 text-[11px] text-muted-foreground">JPG, PNG o WebP · Max 10 MB · Panorámica recomendada</p>
                        <p v-if="coverError" class="mt-1 text-xs text-destructive">{{ coverError }}</p>
                        <p v-else-if="form.errors.cover" class="mt-1 text-xs text-destructive">{{ form.errors.cover }}</p>
                        <input
                            ref="coverInput"
                            type="file"
                            class="hidden"
                            accept="image/jpeg,image/png,image/webp"
                            @change="handleCover"
                        >
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="router.visit(`/clubs/${club.ulid}/teams`)">Cancelar</Button>
                    <Button type="submit" :disabled="form.processing">Crear equipo</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
