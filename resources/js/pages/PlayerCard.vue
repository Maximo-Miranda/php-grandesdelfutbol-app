<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { toPng } from 'html-to-image';
import { Check, Download, Eye, Film, Goal, Loader2, Plus, RefreshCw, Share2, Shield, Sparkles, Trash2, Trophy, UserCircle } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import MinuteSecondInput from '@/components/match/MinuteSecondInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/utils';
import type { BreadcrumbItem, Club, FootballMatch, MatchReel, PlayerProfile } from '@/types';

type PlayerStats = {
    goals: number;
    assists: number;
    matches: number;
    saves: number;
};

type MatchWithVideo = Pick<FootballMatch, 'id' | 'ulid' | 'club_id' | 'title' | 'scheduled_at' | 'video_duration_seconds' | 'duration_minutes'> & { club?: Pick<Club, 'id' | 'ulid' | 'name'> };

type PaginatedReels = { data: MatchReel[]; next_page_url: string | null };

type Props = {
    playerStats: PlayerStats;
    profile: PlayerProfile & { photo_url?: string | null };
    clubs: Club[];
    matchesWithVideo: MatchWithVideo[];
    reels?: PaginatedReels;
};

const props = defineProps<Props>();
const page = usePage();
const user = computed(() => page.props.auth.user);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mis Jugadas', href: '/player-card' },
];

// --- Tabs ---
const activeTab = ref<'reels' | 'card'>('reels');

// --- Reels ---
const allReels = computed(() => props.reels?.data ?? []);
const hasMoreReels = computed(() => !!props.reels?.next_page_url);
const refreshing = ref(false);
const loadingMore = ref(false);

function refreshReels() {
    refreshing.value = true;
    router.reload({
        only: ['reels'],
        preserveScroll: true,
        onFinish: () => { refreshing.value = false; },
    });
}

function loadMoreReels() {
    if (!props.reels?.next_page_url) return;
    loadingMore.value = true;
    router.get(props.reels.next_page_url, {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['reels'],
        onFinish: () => { loadingMore.value = false; },
    });
}

// --- Create reel ---
const showCreateDialog = ref(false);
const selectedClubId = ref('');
const selectedMatchId = ref('');

const clubsWithVideo = computed(() => {
    const ids = new Set(props.matchesWithVideo.map(m => m.club_id));
    return props.clubs.filter(c => ids.has(c.id));
});

const filteredMatches = computed(() =>
    selectedClubId.value
        ? props.matchesWithVideo.filter(m => String(m.club_id) === selectedClubId.value)
        : [],
);

const selectedMatch = computed(() =>
    props.matchesWithVideo.find(m => String(m.id) === selectedMatchId.value),
);

const playerClipTimeInput = ref<InstanceType<typeof MinuteSecondInput> | null>(null);

const playerVideoMaxSeconds = computed(() => {
    if (!selectedMatch.value) return undefined;
    return selectedMatch.value.video_duration_seconds ?? selectedMatch.value.duration_minutes * 60;
});

const createReelForm = useForm({
    minute: 0,
    second: 0,
    request_notes: '',
});

watch(selectedClubId, () => {
    selectedMatchId.value = '';
    createReelForm.reset();
});

watch(selectedMatchId, () => {
    createReelForm.reset();
});

function submitCreateReel() {
    if (!selectedMatch.value?.club) return;

    createReelForm.transform((data) => ({
        minute: data.minute,
        second: data.second,
        request_notes: data.request_notes || null,
    })).post(`/clubs/${selectedMatch.value.club.ulid}/matches/${selectedMatch.value.ulid}/reels/request-player`, {
        preserveScroll: true,
        onSuccess: () => {
            createReelForm.reset();
            selectedClubId.value = '';
            selectedMatchId.value = '';
            showCreateDialog.value = false;
        },
    });
}

function formatTime(totalSeconds: number): string {
    const m = Math.floor(totalSeconds / 60);
    const s = totalSeconds % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
}

function trackView(reel: MatchReel) {
    if (reel.match) {
        const club = props.clubs.find(c => c.id === reel.match!.club_id);
        if (club) {
            router.post(`/clubs/${club.ulid}/matches/${reel.match.ulid}/reels/${reel.ulid}/view`, {}, {
                preserveScroll: true,
                preserveState: true,
            });
        }
    }
}

async function downloadReel(reel: MatchReel) {
    if (!reel.media_url) return;
    const response = await fetch(reel.media_url);
    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${reel.title.replace(/[^a-zA-Z0-9áéíóúñÁÉÍÓÚÑ ]/g, '_')}.mp4`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

async function shareReel(reel: MatchReel) {
    if (!reel.media_url) return;

    const shareData: ShareData = {
        title: reel.title,
        text: `${reel.title} — Grandes del Futbol`,
    };

    if (navigator.canShare) {
        try {
            const response = await fetch(reel.media_url);
            const blob = await response.blob();
            const file = new File([blob], `${reel.title.replace(/[^a-zA-Z0-9]/g, '_')}.mp4`, { type: 'video/mp4' });
            const fileShareData = { ...shareData, files: [file] };
            if (navigator.canShare(fileShareData)) {
                await navigator.share(fileShareData);
                return;
            }
        } catch { /* fall through */ }
    }

    if (navigator.share) {
        try {
            await navigator.share({ ...shareData, url: reel.media_url });
            return;
        } catch { /* cancelled */ }
    }

    await navigator.clipboard.writeText(reel.media_url);
    alert('Enlace copiado al portapapeles');
}

// --- Delete reel ---
const deletingReelUlid = ref<string | null>(null);
const confirmDeleteUlid = ref<string | null>(null);

function deleteReel(reel: MatchReel) {
    if (!reel.match?.club_id) return;
    const club = props.clubs.find(c => c.id === reel.match!.club_id);
    if (!club) return;

    deletingReelUlid.value = reel.ulid;
    router.delete(`/clubs/${club.ulid}/matches/${reel.match.ulid}/reels/${reel.ulid}`, {
        preserveScroll: true,
        onFinish: () => {
            deletingReelUlid.value = null;
            confirmDeleteUlid.value = null;
        },
    });
}

function onDeleteClick(reel: MatchReel) {
    if (reel.status === 'failed' || reel.status === 'pending') {
        deleteReel(reel);
    } else {
        confirmDeleteUlid.value = reel.ulid;
    }
}

// --- Card ---
const cardRef = ref<HTMLElement | null>(null);
const isGenerating = ref(false);
const copied = ref(false);
const avatarDataUrl = ref<string | null>(null);

const initials = computed(() =>
    user.value.name
        .split(' ')
        .map((w: string) => w[0])
        .join('')
        .substring(0, 2)
        .toUpperCase(),
);

const avatarSrc = computed(() => avatarDataUrl.value || props.profile.photo_url);

onMounted(async () => {
    if (!props.profile.photo_url) return;
    try {
        const response = await fetch(props.profile.photo_url);
        const blob = await response.blob();
        const reader = new FileReader();
        reader.onloadend = () => {
            avatarDataUrl.value = reader.result as string;
        };
        reader.readAsDataURL(blob);
    } catch {
        // Fallback to initials
    }
});

async function generateImage(): Promise<Blob | null> {
    if (!cardRef.value) return null;
    isGenerating.value = true;
    try {
        const dataUrl = await toPng(cardRef.value, {
            pixelRatio: 3,
            cacheBust: true,
            skipFonts: true,
        });
        const res = await fetch(dataUrl);
        return await res.blob();
    } catch {
        return null;
    } finally {
        isGenerating.value = false;
    }
}

async function downloadCard() {
    const blob = await generateImage();
    if (!blob) return;
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${user.value.name.replace(/\s+/g, '_')}_GDF.png`;
    a.click();
    URL.revokeObjectURL(url);
}

async function shareCard() {
    const blob = await generateImage();
    if (!blob) return;
    const file = new File([blob], 'mi-tarjeta-gdf.png', { type: 'image/png' });
    if (navigator.share && navigator.canShare?.({ files: [file] })) {
        await navigator.share({ files: [file] });
    } else {
        try {
            await navigator.clipboard.write([
                new ClipboardItem({ 'image/png': blob }),
            ]);
            copied.value = true;
            setTimeout(() => (copied.value = false), 2000);
        } catch {
            downloadCard();
        }
    }
}
</script>

<template>
    <Head title="Mis Jugadas" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-md flex-col items-center px-4 py-6">
            <!-- Header -->
            <div class="mb-4 w-full">
                <h1 class="text-2xl font-bold">Mis Jugadas</h1>
                <p class="flex items-center gap-1 text-sm text-muted-foreground">
                    <Sparkles class="size-3.5 text-primary" />
                    Tus mejores momentos en la cancha
                </p>
            </div>

            <!-- Tab bar -->
            <div class="mb-4 flex w-full rounded-xl border border-border bg-card p-1">
                <button
                    class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-2.5 text-xs font-semibold transition-colors"
                    :class="activeTab === 'reels' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:bg-accent'"
                    @click="activeTab = 'reels'"
                >
                    <Film class="size-3.5" />
                    Jugadas
                </button>
                <button
                    class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-2.5 text-xs font-semibold transition-colors"
                    :class="activeTab === 'card' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:bg-accent'"
                    @click="activeTab = 'card'"
                >
                    <UserCircle class="size-3.5" />
                    Score Card
                </button>
            </div>

            <!-- ===== TAB: REELS ===== -->
            <div v-if="activeTab === 'reels'" class="w-full space-y-3">
                <!-- Create reel button -->
                <Dialog v-if="matchesWithVideo.length" v-model:open="showCreateDialog">
                    <DialogTrigger as-child>
                        <button
                            class="flex w-full items-center gap-3 rounded-xl border border-dashed border-emerald-500/30 bg-emerald-500/5 px-4 py-3 text-left transition-all hover:border-emerald-500/50 hover:bg-emerald-500/10 active:scale-[0.98]"
                        >
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-500/20">
                                <Plus class="size-4 text-emerald-400" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-emerald-400">Crear reel</p>
                                <p class="text-xs text-emerald-300/60">Elige un partido y selecciona el momento</p>
                            </div>
                        </button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Crear reel</DialogTitle>
                            <DialogDescription>
                                Selecciona el partido y el momento que quieres.
                            </DialogDescription>
                        </DialogHeader>
                        <form class="space-y-4" @submit.prevent="submitCreateReel">
                            <div class="grid gap-1.5">
                                <Label class="text-xs">Club</Label>
                                <Select v-model="selectedClubId">
                                    <SelectTrigger class="h-10 text-sm">
                                        <SelectValue placeholder="Selecciona un club" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="c in clubsWithVideo" :key="c.id" :value="String(c.id)">
                                            {{ c.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div v-if="selectedClubId" class="grid gap-1.5">
                                <Label class="text-xs">Partido</Label>
                                <Select v-model="selectedMatchId">
                                    <SelectTrigger class="h-10 text-sm">
                                        <SelectValue placeholder="Selecciona un partido" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="m in filteredMatches" :key="m.id" :value="String(m.id)">
                                            {{ m.title }} · {{ formatDate(m.scheduled_at, { day: 'numeric', month: 'short' }) }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div v-if="selectedMatch">
                                <div class="flex justify-center">
                                    <MinuteSecondInput
                                        ref="playerClipTimeInput"
                                        v-model:minute="createReelForm.minute"
                                        v-model:second="createReelForm.second"
                                        :manual-mode="true"
                                        always-expanded
                                        :max-seconds="playerVideoMaxSeconds"
                                    />
                                </div>
                                <p class="mt-2 text-center text-xs text-muted-foreground">
                                    Se creará un clip de 25s (15s antes y 10s después)
                                </p>
                                <InputError :message="createReelForm.errors.minute" />
                                <InputError :message="createReelForm.errors.second" />
                            </div>

                            <div v-if="selectedMatch" class="grid gap-1.5">
                                <Label for="create-notes" class="text-xs">Notas (opcional)</Label>
                                <Textarea id="create-notes" v-model="createReelForm.request_notes" placeholder="Describe la jugada..." class="text-sm" rows="2" />
                            </div>

                            <div class="flex flex-col gap-2 pt-2">
                                <Button type="submit" class="w-full gap-2" :disabled="createReelForm.processing || !selectedMatch || playerClipTimeInput?.isOverMax">
                                    <Film class="size-4" />
                                    Crear reel
                                </Button>
                                <DialogClose as-child>
                                    <Button type="button" variant="ghost" class="w-full">Cancelar</Button>
                                </DialogClose>
                            </div>
                        </form>
                    </DialogContent>
                </Dialog>

                  <div class="space-y-3">
                    <div
                        v-for="reel in allReels"
                        :key="reel.ulid"
                        class="overflow-hidden rounded-xl border border-border"
                    >
                        <div class="bg-card px-3 py-2.5">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold">{{ reel.title }}</p>
                                    <p v-if="reel.request_notes" class="mt-0.5 text-xs text-foreground/70">{{ reel.request_notes }}</p>
                                    <div class="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
                                        <span>{{ formatTime(reel.start_second) }} - {{ formatTime(reel.end_second) }}</span>
                                        <span v-if="reel.status === 'completed'" class="inline-flex items-center gap-1">
                                            <Eye class="size-3" />
                                            {{ reel.view_count }}
                                        </span>
                                    </div>
                                </div>
                                <span
                                    v-if="reel.status !== 'completed'"
                                    class="mt-0.5 inline-flex shrink-0 items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                                    :class="{
                                        'border-amber-500/30 bg-amber-500/10 text-amber-400': reel.status === 'pending',
                                        'border-blue-500/30 bg-blue-500/10 text-blue-400 animate-pulse': reel.status === 'processing',
                                        'border-red-500/30 bg-red-500/10 text-red-400': reel.status === 'failed',
                                    }"
                                >
                                    {{ reel.status === 'pending' ? 'En cola' : reel.status === 'processing' ? 'Procesando...' : 'Falló' }}
                                </span>
                            </div>
                        </div>

                        <!-- Processing -->
                        <div v-if="reel.status === 'processing'" class="border-t border-blue-500/20 bg-blue-500/5 px-3 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <div class="size-4 animate-spin rounded-full border-2 border-blue-500/30 border-t-blue-400"></div>
                                <p class="text-xs text-blue-400">Generando tu reel...</p>
                            </div>
                            <button
                                class="mt-2 flex w-full items-center justify-center gap-1.5 rounded-lg border border-blue-500/30 py-1.5 text-xs font-medium text-blue-400 transition-colors hover:bg-blue-500/10 disabled:opacity-50"
                                :disabled="refreshing"
                                @click="refreshReels"
                            >
                                <RefreshCw class="size-3" :class="refreshing ? 'animate-spin' : ''" />
                                Actualizar
                            </button>
                        </div>

                        <!-- Pending -->
                        <div v-else-if="reel.status === 'pending'" class="border-t border-amber-500/20 bg-amber-500/5 px-3 py-3">
                            <p class="text-center text-xs text-amber-400">Tu reel está en cola y se procesará pronto.</p>
                            <button
                                class="mt-2 flex w-full items-center justify-center gap-1.5 rounded-lg border border-amber-500/30 py-1.5 text-xs font-medium text-amber-400 transition-colors hover:bg-amber-500/10 disabled:opacity-50"
                                :disabled="refreshing"
                                @click="refreshReels"
                            >
                                <RefreshCw class="size-3" :class="refreshing ? 'animate-spin' : ''" />
                                Actualizar
                            </button>
                        </div>

                        <!-- Failed -->
                        <div v-else-if="reel.status === 'failed'" class="border-t border-red-500/20 bg-red-500/5 px-3 py-3">
                            <p class="text-center text-xs text-red-400">No se pudo generar el reel. Intenta crearlo de nuevo.</p>
                            <button
                                class="mt-2 flex w-full items-center justify-center gap-1.5 rounded-lg border border-red-500/30 py-1.5 text-xs font-medium text-red-400 transition-colors hover:bg-red-500/10"
                                :disabled="deletingReelUlid === reel.ulid || refreshing"
                                @click="deleteReel(reel)"
                            >
                                <Trash2 class="size-3" />
                                Eliminar
                            </button>
                        </div>

                        <!-- Completed -->
                        <template v-else-if="reel.status === 'completed'">
                            <div v-if="reel.media_url" class="border-t border-border bg-black">
                                <video
                                    :src="reel.media_url"
                                    controls
                                    preload="metadata"
                                    class="mx-auto max-h-80 w-full"
                                    @play="trackView(reel)"
                                ></video>
                            </div>
                            <div v-if="reel.media_url" class="flex items-center gap-2 border-t border-border bg-card/50 px-3 py-2.5">
                                <button
                                    class="inline-flex items-center gap-1 rounded-md px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                                    @click="downloadReel(reel)"
                                >
                                    <Download class="size-3.5" />
                                    Descargar
                                </button>
                                <button
                                    v-if="confirmDeleteUlid !== reel.ulid"
                                    class="inline-flex items-center gap-1 rounded-md px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                                    @click="onDeleteClick(reel)"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                                <button
                                    v-else
                                    class="inline-flex items-center gap-1 rounded-md bg-red-500/10 px-2.5 py-1.5 text-xs font-semibold text-red-400 transition-colors hover:bg-red-500/20"
                                    :disabled="deletingReelUlid === reel.ulid || refreshing"
                                    @click="deleteReel(reel)"
                                >
                                    Confirmar
                                </button>
                                <button
                                    class="ml-auto inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition-all hover:bg-emerald-500 active:scale-95"
                                    @click="shareReel(reel)"
                                >
                                    <Share2 class="size-4" />
                                    Compartir
                                </button>
                            </div>
                        </template>
                    </div>
                  </div>

                <Button
                    v-if="hasMoreReels"
                    variant="outline"
                    class="w-full"
                    :disabled="loadingMore"
                    @click="loadMoreReels"
                >
                    {{ loadingMore ? 'Cargando...' : 'Ver más jugadas' }}
                </Button>

                <div v-if="!allReels.length" class="py-8 text-center text-sm text-muted-foreground">
                    <p v-if="matchesWithVideo.length">
                        Aún no tienes jugadas. Tus goles y jugadas destacadas aparecerán aquí, o crea un reel desde un partido con video.
                    </p>
                    <p v-else-if="clubs.length">
                        Aún no tienes jugadas. Tus clubes no tienen partidos con video. Cuando un administrador suba un video de un partido, podrás crear reels.
                    </p>
                    <p v-else>
                        Aún no tienes jugadas. Únete a un club para empezar.
                    </p>
                </div>
            </div>

            <!-- ===== TAB: SCORE CARD ===== -->
            <div v-if="activeTab === 'card'" class="w-full">
                <!-- Card actions -->
                <div class="mb-4 flex justify-end gap-2">
                    <Button
                        variant="outline"
                        size="icon"
                        :disabled="isGenerating"
                        title="Descargar imagen"
                        @click="downloadCard"
                    >
                        <Loader2 v-if="isGenerating" class="size-4 animate-spin" />
                        <Download v-else class="size-4" />
                    </Button>
                    <Button
                        :disabled="isGenerating"
                        size="icon"
                        :title="copied ? 'Copiado!' : 'Compartir en redes'"
                        @click="shareCard"
                    >
                        <Loader2 v-if="isGenerating" class="size-4 animate-spin" />
                        <Check v-else-if="copied" class="size-4 text-green-500" />
                        <Share2 v-else class="size-4" />
                    </Button>
                </div>

                <!-- Card preview -->
                <div
                    ref="cardRef"
                    class="relative overflow-hidden rounded-2xl"
                    style="aspect-ratio: 9/16; background: linear-gradient(160deg, #0f172a 0%, #1e293b 40%, #0f4c3a 70%, #064e3b 100%); font-family: 'Public Sans', system-ui, -apple-system, sans-serif;"
                >
                    <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle at 25% 25%, rgba(255,255,255,0.1) 1px, transparent 1px); background-size: 20px 20px;" />
                    <div class="absolute left-1/2 top-1/3 size-64 -translate-x-1/2 -translate-y-1/2 rounded-full bg-emerald-500/10 blur-3xl" />

                    <div class="relative z-10 flex h-full flex-col items-center justify-between p-8">
                        <!-- Top: GDF Logo -->
                        <div class="flex items-center gap-2">
                            <AppLogoIcon class="size-7 text-emerald-400" />
                            <span class="text-sm font-bold tracking-wider text-white/80">GRANDES DEL FUTBOL</span>
                        </div>

                        <!-- Center: Player info -->
                        <div class="flex flex-col items-center">
                            <!-- Avatar -->
                            <div class="mb-5 flex size-32 items-center justify-center overflow-hidden rounded-full border-4 border-emerald-400/30 bg-white/10 shadow-xl shadow-emerald-500/20 ring-2 ring-emerald-400/10 ring-offset-2 ring-offset-transparent">
                                <img v-if="avatarSrc" :src="avatarSrc" alt="Foto" class="size-full object-cover" />
                                <span v-else class="text-5xl font-bold text-white/60">{{ initials }}</span>
                            </div>

                            <!-- Name (full name, not nickname) -->
                            <h2 class="mb-1 text-center text-2xl font-extrabold uppercase tracking-tight text-white">
                                {{ user.name }}
                            </h2>
                            <p v-if="profile.preferred_position" class="mb-8 rounded-full bg-emerald-500/20 px-4 py-1 text-xs font-bold uppercase tracking-wider text-emerald-400">
                                {{ profile.preferred_position }}
                            </p>

                            <!-- Stats grid -->
                            <div class="grid w-full max-w-xs gap-3" :class="playerStats.saves > 0 || playerStats.assists > 0 ? 'grid-cols-2' : 'grid-cols-3'">
                                <div class="flex flex-col items-center rounded-xl bg-white/5 p-3 backdrop-blur-sm">
                                    <Trophy class="mb-1 size-5 text-emerald-400" />
                                    <span class="text-3xl font-extrabold text-white">{{ playerStats.matches }}</span>
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-white/50">Partidos</span>
                                </div>
                                <div class="flex flex-col items-center rounded-xl bg-white/5 p-3 backdrop-blur-sm">
                                    <Goal class="mb-1 size-5 text-green-400" />
                                    <span class="text-3xl font-extrabold text-white">{{ playerStats.goals }}</span>
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-white/50">Goles</span>
                                </div>
                                <div class="flex flex-col items-center rounded-xl bg-white/5 p-3 backdrop-blur-sm">
                                    <Shield class="mb-1 size-5 text-blue-400" />
                                    <span class="text-3xl font-extrabold text-white">{{ clubs.length }}</span>
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-white/50">Clubes</span>
                                </div>
                                <div v-if="playerStats.assists > 0" class="flex flex-col items-center rounded-xl bg-white/5 p-3 backdrop-blur-sm">
                                    <Sparkles class="mb-1 size-5 text-sky-400" />
                                    <span class="text-3xl font-extrabold text-white">{{ playerStats.assists }}</span>
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-white/50">Asistencias</span>
                                </div>
                                <div v-if="playerStats.saves > 0" class="flex flex-col items-center rounded-xl bg-white/5 p-3 backdrop-blur-sm">
                                    <Shield class="mb-1 size-5 text-violet-400" />
                                    <span class="text-3xl font-extrabold text-white">{{ playerStats.saves }}</span>
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-white/50">Atajadas</span>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom: watermark -->
                        <p class="text-[10px] font-medium tracking-widest text-white/30">grandesdelfutbol.com</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
