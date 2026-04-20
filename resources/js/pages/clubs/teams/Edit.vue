<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Camera, Check, ImagePlus, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ColorSwatchPicker from '@/components/ColorSwatchPicker.vue';
import PlayerPicker from '@/components/PlayerPicker.vue';
import TournamentToggle from '@/components/TournamentToggle.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club } from '@/types';

type PlayerOption = { id: number; ulid: string; name: string; jersey_number: number | null; position: string | null };
type Team = {
    id: number; ulid: string; name: string; color: string; bio: string | null;
    is_tournament: boolean;
    logo_url: string | null; cover_url: string | null;
    coach: PlayerOption | null; captain: PlayerOption | null;
    players: PlayerOption[];
    season: { ulid: string; name: string; is_active: boolean };
};

const props = defineProps<{
    club: Club;
    team: Team;
    players: PlayerOption[];
}>();

const selectedPlayerIds = ref<number[]>(props.team.players.map(p => p.id));

const form = useForm({
    _method: 'PATCH',
    name: props.team.name,
    color: props.team.color,
    coach_player_id: props.team.coach?.id ?? null,
    captain_player_id: props.team.captain?.id ?? null,
    bio: props.team.bio ?? '',
    is_tournament: props.team.is_tournament,
    player_ids: selectedPlayerIds.value,
    logo: null as File | null,
    cover: null as File | null,
    remove_logo: false,
    remove_cover: false,
});

function toggle(id: number): void {
    const idx = selectedPlayerIds.value.indexOf(id);
    if (idx >= 0) selectedPlayerIds.value.splice(idx, 1);
    else selectedPlayerIds.value.push(id);
    form.player_ids = [...selectedPlayerIds.value];
}

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

const initial = computed(() => form.name?.charAt(0).toUpperCase() || '?');
const displayLogo = computed(() => {
    if (logoPreview.value) return logoPreview.value;
    if (form.remove_logo) return null;
    return props.team.logo_url;
});
const displayCover = computed(() => {
    if (coverPreview.value) return coverPreview.value;
    if (form.remove_cover) return null;
    return props.team.cover_url;
});

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
    form.remove_logo = false;
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
    form.remove_cover = false;
    coverPreview.value = URL.createObjectURL(file);
}
function selectLogo(): void { logoInput.value?.click(); }
function selectCover(): void { coverInput.value?.click(); }
function removeLogo(): void {
    form.logo = null;
    logoPreview.value = null;
    logoError.value = null;
    form.remove_logo = true;
    if (logoInput.value) logoInput.value.value = '';
}
function removeCover(): void {
    form.cover = null;
    coverPreview.value = null;
    coverError.value = null;
    form.remove_cover = true;
    if (coverInput.value) coverInput.value.value = '';
}

function submit(): void {
    form.post(`/clubs/${props.club.ulid}/teams/${props.team.ulid}`, { forceFormData: true });
}

function destroy(): void {
    if (!confirm('¿Eliminar este equipo? Solo es posible si no tiene partidos asociados.')) return;
    router.delete(`/clubs/${props.club.ulid}/teams/${props.team.ulid}`);
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubes', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
    { title: 'Posiciones', href: `/clubs/${props.club.ulid}/standings` },
    { title: 'Equipos', href: `/clubs/${props.club.ulid}/teams` },
    { title: props.team.name, href: `/clubs/${props.club.ulid}/teams/${props.team.ulid}` },
    { title: 'Editar', href: `/clubs/${props.club.ulid}/teams/${props.team.ulid}/edit` },
];
</script>

<template>
    <Head :title="`Editar ${team.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <h1 class="mb-6 text-2xl font-bold">Editar equipo</h1>

            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium">Nombre</label>
                    <Input v-model="form.name" required maxlength="50" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-destructive">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Color</label>
                    <ColorSwatchPicker v-model="form.color" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Director Técnico</label>
                        <PlayerPicker v-model="form.coach_player_id" :players="players" show-position />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Capitán</label>
                        <PlayerPicker v-model="form.captain_player_id" :players="players" />
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Descripción</label>
                    <Textarea v-model="form.bio" maxlength="1000" rows="3" />
                </div>

                <TournamentToggle v-model="form.is_tournament" />

                <!-- Imágenes -->
                <div class="grid gap-4 sm:grid-cols-[auto_1fr]">
                    <div>
                        <label class="mb-2 block text-sm font-medium">Escudo</label>
                        <div class="relative size-24">
                            <button
                                type="button"
                                class="group relative flex size-full items-center justify-center overflow-hidden rounded-full border-2 border-dashed border-border bg-muted/40 text-2xl font-bold text-muted-foreground transition hover:border-primary/60 hover:bg-muted"
                                :style="!displayLogo ? { background: `linear-gradient(135deg, ${form.color}22, ${form.color}11)` } : {}"
                                @click="selectLogo"
                            >
                                <img v-if="displayLogo" :src="displayLogo" alt="Escudo" class="size-full object-cover">
                                <span v-else class="text-foreground/70">{{ initial }}</span>
                                <div class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 transition group-hover:opacity-100">
                                    <Camera class="size-5 text-white" />
                                </div>
                            </button>
                            <button
                                v-if="displayLogo"
                                type="button"
                                title="Eliminar escudo"
                                aria-label="Eliminar escudo"
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

                    <div>
                        <label class="mb-2 block text-sm font-medium">Portada</label>
                        <div class="relative h-24 w-full">
                            <button
                                type="button"
                                class="group relative flex size-full items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-border bg-muted/40 transition hover:border-primary/60 hover:bg-muted"
                                :style="!displayCover ? { background: `linear-gradient(135deg, ${form.color}33, ${form.color}11)` } : {}"
                                @click="selectCover"
                            >
                                <img v-if="displayCover" :src="displayCover" alt="Portada" class="size-full object-cover">
                                <div v-else class="flex flex-col items-center gap-1 text-muted-foreground">
                                    <ImagePlus class="size-5" />
                                    <span class="text-[11px] font-medium">Subir portada</span>
                                </div>
                                <div class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 transition group-hover:opacity-100">
                                    <Camera class="size-5 text-white" />
                                </div>
                            </button>
                            <button
                                v-if="displayCover"
                                type="button"
                                title="Eliminar portada"
                                aria-label="Eliminar portada"
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

                <div>
                    <label class="mb-1 block text-sm font-medium">Plantilla</label>
                    <p class="mb-2 text-xs text-muted-foreground">Agrega o quita jugadores manualmente. Los que confirmen asistencia con este equipo se agregarán automáticamente.</p>
                    <div v-if="players.length === 0" class="rounded-md border border-dashed border-border/70 p-4 text-center">
                        <p class="text-sm font-medium text-foreground">No hay jugadores disponibles</p>
                        <p v-if="!form.is_tournament" class="mt-1 text-xs text-muted-foreground">
                            Todos los jugadores del club ya pertenecen a otro equipo regular de esta temporada. Para mover alguno, primero quítalo del otro equipo o marca este como
                            <span class="font-semibold text-foreground">Equipo de torneo</span>
                            (permite plantilla compartida).
                        </p>
                        <p v-else class="mt-1 text-xs text-muted-foreground">
                            Aún no hay jugadores registrados en este club. Invita miembros o crea jugadores desde la sección correspondiente.
                        </p>
                    </div>
                    <div v-else class="max-h-64 space-y-1 overflow-y-auto rounded-md border p-2">
                        <button
                            v-for="p in players"
                            :key="p.id"
                            type="button"
                            class="flex w-full items-center justify-between rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                            :class="selectedPlayerIds.includes(p.id) ? 'bg-emerald-500/10' : ''"
                            @click="toggle(p.id)"
                        >
                            <span>{{ p.name }}{{ p.jersey_number ? ` #${p.jersey_number}` : '' }}</span>
                            <Check v-if="selectedPlayerIds.includes(p.id)" class="size-4 text-emerald-500" />
                        </button>
                    </div>
                </div>

                <div class="flex justify-between">
                    <Button type="button" variant="destructive" @click="destroy">
                        <Trash2 class="mr-1.5 size-3.5" />Eliminar
                    </Button>
                    <div class="flex gap-2">
                        <Button type="button" variant="outline" @click="router.visit(`/clubs/${club.ulid}/teams/${team.ulid}`)">Cancelar</Button>
                        <Button type="submit" :disabled="form.processing">Guardar</Button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
