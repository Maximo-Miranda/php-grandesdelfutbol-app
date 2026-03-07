<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeftRight,
    Calendar,
    ChevronDown,
    ChevronUp,
    CircleDot,
    Clock,
    MapPin,
    Minus,
    Pencil,
    Play,
    Plus,
    Check,
    Search,
    Users,
    UserMinus,
    Video,
    RectangleVertical,
    Shield,
    Star,
    Trash2,
    Trophy,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, FootballMatch, MatchEvent, Player } from '@/types';

type Props = { club: Club; match: FootballMatch; isAdmin?: boolean; players?: Player[] };
const props = defineProps<Props>();

const base = `/clubs/${props.club.ulid}/matches`;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
    { title: 'Partidos', href: base },
    { title: props.match.title, href: `${base}/${props.match.ulid}` },
];

// --- Goals ---
function countTeamGoals(team: 'a' | 'b'): number {
    const opposite = team === 'a' ? 'b' : 'a';
    return (props.match.events ?? []).filter((e: MatchEvent) => {
        const playerTeam = props.match.attendances?.find(a => a.player_id === e.player_id)?.team;
        if (e.event_type === 'goal' || e.event_type === 'penalty_scored') return playerTeam === team;
        if (e.event_type === 'own_goal') return playerTeam === opposite;
        return false;
    }).length;
}

const teamAGoals = computed(() => countTeamGoals('a'));
const teamBGoals = computed(() => countTeamGoals('b'));

// --- Events ---
const sortedEvents = computed(() => [...(props.match.events ?? [])].sort((a, b) => a.minute - b.minute));

const eventLabel: Record<string, string> = {
    goal: 'Gol',
    assist: 'Asistencia',
    yellow_card: 'Tarjeta amarilla',
    red_card: 'Tarjeta roja',
    penalty_scored: 'Penal anotado',
    penalty_missed: 'Penal fallado',
    own_goal: 'Autogol',
    save: 'Atajada',
    free_kick: 'Tiro libre',
    substitution: 'Cambio',
    injury: 'Lesión',
    foul: 'Falta',
};

function getPlayerTeam(playerId: number): 'a' | 'b' | null {
    const att = props.match.attendances?.find(a => a.player_id === playerId);
    return att?.team ?? null;
}

// --- Player stats ---
type PlayerStat = {
    name: string;
    ulid: string;
    team: 'a' | 'b' | null;
    jerseyNumber: number | null;
    goals: number;
    assists: number;
    yellowCards: number;
    redCards: number;
};

const playerStats = computed(() => {
    const map = new Map<number, PlayerStat>();
    for (const event of props.match.events ?? []) {
        if (!map.has(event.player_id)) {
            const att = props.match.attendances?.find(a => a.player_id === event.player_id);
            map.set(event.player_id, {
                name: event.player?.display_name ?? 'Unknown',
                ulid: event.player?.ulid ?? '',
                team: att?.team as 'a' | 'b' | null ?? null,
                jerseyNumber: event.player?.jersey_number ?? null,
                goals: 0,
                assists: 0,
                yellowCards: 0,
                redCards: 0,
            });
        }
        const stat = map.get(event.player_id)!;
        if (event.event_type === 'goal') stat.goals++;
        if (event.event_type === 'assist') stat.assists++;
        if (event.event_type === 'yellow_card') stat.yellowCards++;
        if (event.event_type === 'red_card') stat.redCards++;
    }
    return map;
});

const teamAStats = computed(() =>
    [...playerStats.value.entries()].filter(([, s]) => s.team === 'a').map(([id, s]) => ({ id, ...s })),
);

const teamBStats = computed(() =>
    [...playerStats.value.entries()].filter(([, s]) => s.team === 'b').map(([id, s]) => ({ id, ...s })),
);

// --- Top scorer ---
const topScorer = computed(() => {
    let best: { name: string; goals: number; team: 'a' | 'b' | null } | null = null;
    for (const [, stat] of playerStats.value) {
        if (stat.goals > 0 && (!best || stat.goals > best.goals)) {
            best = { name: stat.name, goals: stat.goals, team: stat.team };
        }
    }
    return best;
});

// --- Date helpers ---
const scheduledDate = new Date(props.match.scheduled_at);

const formattedDate = computed(() =>
    scheduledDate.toLocaleDateString('es', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).replace(/^\w/, c => c.toUpperCase()),
);

const formattedTime = computed(() =>
    scheduledDate.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit', hour12: false }),
);

const matchDuration = computed(() => {
    if (props.match.started_at && props.match.ended_at) {
        const start = new Date(props.match.started_at).getTime();
        const end = new Date(props.match.ended_at).getTime();
        return Math.round((end - start) / 60000);
    }
    return props.match.duration_minutes;
});

// --- Team color helpers ---
function teamColor(team: 'a' | 'b' | null): string {
    if (team === 'a') return props.match.team_a_color ?? '#6b7280';
    if (team === 'b') return props.match.team_b_color ?? '#6b7280';
    return '#6b7280';
}

function finalizeStats() {
    router.post(`${base}/${props.match.ulid}/finalize-stats`);
}

const showDeleteDialog = ref(false);
function deleteMatch() {
    router.delete(`${base}/${props.match.ulid}`, {
        onSuccess: () => { showDeleteDialog.value = false; },
    });
}

function extractYoutubeId(url: string): string | null {
    const match = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/))([a-zA-Z0-9_-]{11})/);
    return match?.[1] ?? null;
}

const youtubeId = computed(() => props.match.youtube_url ? extractYoutubeId(props.match.youtube_url) : null);

// --- YouTube URL inline edit (admin) ---
const youtubeInput = ref(props.match.youtube_url ?? '');
const savingYoutube = ref(false);

function saveYoutubeUrl() {
    savingYoutube.value = true;
    router.put(`${base}/${props.match.ulid}`, {
        title: props.match.title,
        scheduled_at: props.match.scheduled_at,
        duration_minutes: props.match.duration_minutes,
        arrival_minutes: props.match.arrival_minutes,
        max_players: props.match.max_players,
        max_substitutes: props.match.max_substitutes,
        registration_opens_hours: props.match.registration_opens_hours,
        youtube_url: youtubeInput.value || null,
    }, {
        preserveScroll: true,
        onFinish: () => { savingYoutube.value = false; },
    });
}

function formatStatsDate(dateStr: string): string {
    const d = new Date(dateStr);
    return d.toLocaleDateString('es', { day: 'numeric', month: 'short', year: 'numeric' });
}

// --- Manage players (admin) ---
const showManagePlayers = ref(false);
const playerSearchQuery = ref('');
const addingPlayerId = ref<number | null>(null);
const removingAttendanceUlid = ref<string | null>(null);

const registeredPlayerIds = computed(() =>
    new Set((props.match.attendances ?? []).map(a => a.player_id)),
);

const unregisteredPlayers = computed(() =>
    (props.players ?? []).filter(p => !registeredPlayerIds.value.has(p.id)),
);

const filteredUnregisteredPlayers = computed(() => {
    const q = playerSearchQuery.value.toLowerCase().trim();
    if (!q) return unregisteredPlayers.value;
    return unregisteredPlayers.value.filter(p =>
        p.display_name.toLowerCase().includes(q)
        || p.position_label?.toLowerCase().includes(q)
        || String(p.jersey_number ?? '').includes(q),
    );
});

function addPlayerToMatch(playerId: number, team: 'a' | 'b') {
    addingPlayerId.value = playerId;
    router.post(`${base}/${props.match.ulid}/attendance`, {
        player_id: playerId,
        status: 'confirmed',
        team,
    }, {
        preserveScroll: true,
        onFinish: () => { addingPlayerId.value = null; },
    });
}

function removePlayerFromMatch(attendanceUlid: string) {
    removingAttendanceUlid.value = attendanceUlid;
    router.delete(`${base}/${props.match.ulid}/attendance/${attendanceUlid}`, {
        preserveScroll: true,
        onFinish: () => { removingAttendanceUlid.value = null; },
    });
}

// --- Edit events (admin) ---
const showEditEvents = ref(false);
const editSelectedPlayerId = ref<number | null>(null);
const editSelectedPlayerName = ref('');
const editMinute = ref(0);
const editSubmitting = ref(false);

const editEventTypes = [
    { value: 'goal', label: 'Gol', icon: CircleDot, color: 'text-emerald-400', bg: 'bg-emerald-500/10 border-emerald-500/30 hover:bg-emerald-500/20' },
    { value: 'assist', label: 'Asist.', icon: CircleDot, color: 'text-sky-400', bg: 'bg-sky-500/10 border-sky-500/30 hover:bg-sky-500/20' },
    { value: 'yellow_card', label: 'Amarilla', icon: RectangleVertical, color: 'text-yellow-400', bg: 'bg-yellow-500/10 border-yellow-500/30 hover:bg-yellow-500/20' },
    { value: 'red_card', label: 'Roja', icon: RectangleVertical, color: 'text-red-400', bg: 'bg-red-500/10 border-red-500/30 hover:bg-red-500/20' },
    { value: 'own_goal', label: 'Autogol', icon: Shield, color: 'text-orange-400', bg: 'bg-orange-500/10 border-orange-500/30 hover:bg-orange-500/20' },
    { value: 'penalty_scored', label: 'Penal', icon: CircleDot, color: 'text-emerald-300', bg: 'bg-emerald-500/10 border-emerald-500/30 hover:bg-emerald-500/20' },
    { value: 'penalty_missed', label: 'Penal fallado', icon: CircleDot, color: 'text-zinc-400', bg: 'bg-zinc-500/10 border-zinc-500/30 hover:bg-zinc-500/20' },
    { value: 'save', label: 'Atajada', icon: Shield, color: 'text-violet-400', bg: 'bg-violet-500/10 border-violet-500/30 hover:bg-violet-500/20' },
    { value: 'foul', label: 'Falta', icon: X, color: 'text-amber-400', bg: 'bg-amber-500/10 border-amber-500/30 hover:bg-amber-500/20' },
    { value: 'substitution', label: 'Cambio', icon: ArrowLeftRight, color: 'text-blue-400', bg: 'bg-blue-500/10 border-blue-500/30 hover:bg-blue-500/20' },
    { value: 'injury', label: 'Lesión', icon: AlertTriangle, color: 'text-rose-400', bg: 'bg-rose-500/10 border-rose-500/30 hover:bg-rose-500/20' },
    { value: 'free_kick', label: 'Tiro libre', icon: CircleDot, color: 'text-cyan-400', bg: 'bg-cyan-500/10 border-cyan-500/30 hover:bg-cyan-500/20' },
];

const confirmedPlayers = computed(() =>
    props.match.attendances?.filter(a => a.status === 'confirmed') ?? [],
);

function selectEditPlayer(playerId: number, playerName: string) {
    if (editSelectedPlayerId.value === playerId) {
        editSelectedPlayerId.value = null;
        editSelectedPlayerName.value = '';
    } else {
        editSelectedPlayerId.value = playerId;
        editSelectedPlayerName.value = playerName;
    }
}

function addEvent(eventType: string) {
    if (!editSelectedPlayerId.value || editSubmitting.value) return;
    editSubmitting.value = true;

    router.post(`${base}/${props.match.ulid}/events`, {
        player_id: editSelectedPlayerId.value,
        event_type: eventType,
        minute: editMinute.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            editSelectedPlayerId.value = null;
            editSelectedPlayerName.value = '';
            editSubmitting.value = false;
        },
        onError: () => { editSubmitting.value = false; },
    });
}

function removeEvent(eventUlid: string) {
    router.delete(`${base}/${props.match.ulid}/events/${eventUlid}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Resumen del Partido" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <!-- ===== SCOREBOARD HERO ===== -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-b from-emerald-950 via-emerald-950/90 to-zinc-950 p-6 shadow-lg">
                <!-- Pitch decoration -->
                <div class="pointer-events-none absolute inset-0 opacity-[0.05]">
                    <div class="absolute top-1/2 left-1/2 size-32 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white"></div>
                    <div class="absolute inset-y-0 left-1/2 w-px bg-white"></div>
                </div>

                <div class="relative text-center">
                    <!-- Status -->
                    <span class="inline-block rounded-full border border-blue-500/30 bg-blue-500/20 px-3 py-0.5 text-[10px] font-bold tracking-widest text-blue-300 uppercase">
                        FINALIZADO
                    </span>

                    <p class="mt-2 text-sm font-medium text-zinc-400">{{ match.title }}</p>

                    <!-- Score with team colors -->
                    <div class="mt-4 flex items-center justify-center gap-4 sm:gap-8">
                        <div class="min-w-0 flex-1 text-right">
                            <div class="mb-2 flex items-center justify-end gap-2">
                                <p class="truncate text-xs font-bold tracking-wider text-zinc-400 uppercase sm:text-sm">{{ match.team_a_name }}</p>
                                <span class="size-3 shrink-0 rounded-sm" :style="{ backgroundColor: match.team_a_color }"></span>
                            </div>
                            <p class="text-5xl font-black tabular-nums text-white sm:text-6xl">{{ teamAGoals }}</p>
                        </div>

                        <div class="flex flex-col items-center">
                            <span class="text-xl font-light text-zinc-600 select-none">vs</span>
                        </div>

                        <div class="min-w-0 flex-1 text-left">
                            <div class="mb-2 flex items-center gap-2">
                                <span class="size-3 shrink-0 rounded-sm" :style="{ backgroundColor: match.team_b_color }"></span>
                                <p class="truncate text-xs font-bold tracking-wider text-zinc-400 uppercase sm:text-sm">{{ match.team_b_name }}</p>
                            </div>
                            <p class="text-5xl font-black tabular-nums text-white sm:text-6xl">{{ teamBGoals }}</p>
                        </div>
                    </div>

                    <!-- Top scorer highlight -->
                    <div v-if="topScorer" class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-amber-500/10 px-3 py-1 text-sm text-amber-400">
                        <Trophy class="size-3.5" />
                        <span class="font-semibold">{{ topScorer.name }}</span>
                        <span class="text-amber-500/60">&mdash; {{ topScorer.goals }} {{ topScorer.goals === 1 ? 'gol' : 'goles' }}</span>
                    </div>
                </div>
            </div>

            <!-- ===== YOUTUBE VIDEO ===== -->
            <a
                v-if="youtubeId"
                :href="match.youtube_url!"
                target="_blank"
                rel="noopener noreferrer"
                class="group relative mt-4 block overflow-hidden rounded-xl"
            >
                <img
                    :src="`https://img.youtube.com/vi/${youtubeId}/maxresdefault.jpg`"
                    :alt="match.title"
                    class="aspect-video w-full object-cover transition-transform duration-300 group-hover:scale-105"
                />
                <div class="absolute inset-0 flex items-center justify-center bg-black/30 transition-colors group-hover:bg-black/40">
                    <div class="flex size-16 items-center justify-center rounded-full bg-red-600 shadow-lg transition-transform group-hover:scale-110">
                        <Play class="ml-1 size-7 fill-white text-white" />
                    </div>
                </div>
                <div class="absolute bottom-0 inset-x-0 bg-gradient-to-t from-black/80 to-transparent px-4 pb-3 pt-8">
                    <p class="text-sm font-semibold text-white">Ver video del partido</p>
                </div>
            </a>

            <!-- YouTube URL inline edit (admin) -->
            <div v-if="isAdmin" class="mt-3 flex items-center gap-2">
                <div class="relative flex-1">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <Video class="size-4 text-muted-foreground" />
                    </div>
                    <Input
                        v-model="youtubeInput"
                        :placeholder="youtubeId ? 'Cambiar enlace de YouTube...' : 'Pegar enlace de YouTube del partido...'"
                        class="pl-9 text-sm"
                        @keydown.enter.prevent="saveYoutubeUrl"
                    />
                </div>
                <Button
                    size="sm"
                    :disabled="savingYoutube || youtubeInput === (match.youtube_url ?? '')"
                    class="shrink-0 gap-1.5"
                    @click="saveYoutubeUrl"
                >
                    <Check class="size-3.5" />
                    Guardar
                </Button>
            </div>

            <!-- ===== MATCH INFO STRIP ===== -->
            <div class="mt-4 grid grid-cols-3 divide-x divide-border rounded-xl border border-border bg-card">
                <div class="flex flex-col items-center gap-1 px-2 py-3">
                    <Calendar class="size-4 text-muted-foreground" />
                    <p class="text-center text-xs font-medium">{{ formattedDate }}</p>
                    <p class="text-xs text-muted-foreground">{{ formattedTime }}</p>
                </div>
                <div class="flex flex-col items-center gap-1 px-2 py-3">
                    <Clock class="size-4 text-muted-foreground" />
                    <p class="text-xs font-medium">{{ matchDuration }}'</p>
                    <p class="text-xs text-muted-foreground">Duracion</p>
                </div>
                <div class="flex flex-col items-center gap-1 px-2 py-3">
                    <MapPin class="size-4 text-muted-foreground" />
                    <p class="text-center text-xs font-medium">{{ match.field?.name ?? 'Sin cancha' }}</p>
                    <p v-if="match.field?.field_type" class="text-xs text-muted-foreground">{{ match.field.field_type }}</p>
                </div>
            </div>

            <!-- Venue details -->
            <div v-if="match.field?.venue" class="mt-2 rounded-xl border border-border bg-card px-4 py-3 text-center text-sm text-muted-foreground">
                {{ match.field.venue.name }}
                <span v-if="match.field.venue.address"> &mdash; {{ match.field.venue.address }}</span>
            </div>

            <!-- Stats finalized badge -->
            <div v-if="match.stats_finalized_at" class="mt-3 text-center">
                <Badge variant="secondary" class="gap-1">
                    <Star class="size-3" />
                    Estadisticas acumuladas el {{ formatStatsDate(match.stats_finalized_at) }}
                </Badge>
            </div>

            <!-- ===== TIMELINE ===== -->
            <div v-if="sortedEvents.length" class="mt-6">
                <h3 class="mb-4 text-[10px] font-bold tracking-widest text-muted-foreground uppercase">
                    Timeline ({{ sortedEvents.length }})
                </h3>

                <div class="relative space-y-0">
                    <!-- Center line -->
                    <div class="absolute inset-y-0 left-1/2 w-px -translate-x-1/2 bg-border"></div>

                    <div
                        v-for="event in sortedEvents"
                        :key="event.id"
                        class="relative flex items-center gap-2 py-1.5"
                        :class="getPlayerTeam(event.player_id) === 'b' ? 'flex-row-reverse' : ''"
                    >
                        <!-- Event card -->
                        <div
                            class="flex min-w-0 flex-1 items-center gap-2 rounded-lg border px-3 py-2"
                            :class="getPlayerTeam(event.player_id) === 'b' ? 'flex-row-reverse text-right' : ''"
                            :style="{ borderColor: teamColor(getPlayerTeam(event.player_id)) + '40', backgroundColor: teamColor(getPlayerTeam(event.player_id)) + '08' }"
                        >
                            <CircleDot v-if="event.event_type === 'goal' || event.event_type === 'penalty_scored'" class="size-3.5 shrink-0 text-emerald-400" />
                            <RectangleVertical v-else-if="event.event_type === 'yellow_card'" class="size-3.5 shrink-0 text-yellow-400" />
                            <RectangleVertical v-else-if="event.event_type === 'red_card'" class="size-3.5 shrink-0 text-red-400" />
                            <Shield v-else-if="event.event_type === 'save'" class="size-3.5 shrink-0 text-violet-400" />
                            <CircleDot v-else class="size-3.5 shrink-0 text-muted-foreground" />

                            <div class="min-w-0 flex-1">
                                <Link
                                    v-if="event.player"
                                    :href="`/clubs/${club.ulid}/players/${event.player.ulid}`"
                                    class="block truncate text-sm font-medium hover:text-primary hover:underline"
                                >{{ event.player.display_name }}</Link>
                                <p class="text-[10px] text-muted-foreground">{{ eventLabel[event.event_type] ?? event.event_type }}</p>
                            </div>
                            <button
                                v-if="isAdmin && showEditEvents"
                                class="shrink-0 text-destructive/50 transition-opacity hover:text-destructive"
                                @click="removeEvent(event.ulid)"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </div>

                        <!-- Minute bubble (center) -->
                        <span class="z-10 flex size-9 shrink-0 items-center justify-center rounded-full border border-border bg-card text-xs font-bold tabular-nums">
                            {{ event.minute }}'
                        </span>

                        <!-- Spacer for the other side -->
                        <div class="flex-1"></div>
                    </div>
                </div>
            </div>

            <div v-else class="mt-6 text-center text-sm text-muted-foreground">
                No se registraron eventos en este partido.
            </div>

            <!-- ===== PLAYER STATS BY TEAM ===== -->
            <div v-if="playerStats.size" class="mt-6 space-y-4">
                <h3 class="text-[10px] font-bold tracking-widest text-muted-foreground uppercase">
                    Estadisticas por jugador
                </h3>

                <!-- Team A stats -->
                <div v-if="teamAStats.length" class="overflow-hidden rounded-xl border border-border">
                    <div class="flex items-center gap-2.5 px-4 py-2.5" :style="{ backgroundColor: match.team_a_color + '20' }">
                        <span class="size-4 shrink-0 rounded-sm" :style="{ backgroundColor: match.team_a_color }"></span>
                        <span class="flex-1 text-sm font-bold">{{ match.team_a_name }}</span>
                    </div>
                    <div class="divide-y divide-border/50">
                        <div
                            v-for="stat in teamAStats"
                            :key="stat.id"
                            class="flex items-center gap-3 bg-card px-4 py-2.5"
                        >
                            <div
                                class="flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                :style="{ backgroundColor: match.team_a_color + '30' }"
                            >
                                {{ stat.jerseyNumber ?? stat.name.charAt(0).toUpperCase() }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <Link :href="`/clubs/${club.ulid}/players/${stat.ulid}`" class="block truncate text-sm font-medium hover:text-primary hover:underline">{{ stat.name }}</Link>
                                <div class="flex flex-wrap gap-3 text-xs text-muted-foreground">
                                    <span v-if="stat.goals" class="flex items-center gap-1">
                                        <CircleDot class="size-3 text-emerald-400" /> {{ stat.goals }} {{ stat.goals === 1 ? 'gol' : 'goles' }}
                                    </span>
                                    <span v-if="stat.assists">{{ stat.assists }} {{ stat.assists === 1 ? 'asist.' : 'asist.' }}</span>
                                    <span v-if="stat.yellowCards" class="flex items-center gap-1">
                                        <RectangleVertical class="size-3 text-yellow-400" /> {{ stat.yellowCards }}
                                    </span>
                                    <span v-if="stat.redCards" class="flex items-center gap-1">
                                        <RectangleVertical class="size-3 text-red-400" /> {{ stat.redCards }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Team B stats -->
                <div v-if="teamBStats.length" class="overflow-hidden rounded-xl border border-border">
                    <div class="flex items-center gap-2.5 px-4 py-2.5" :style="{ backgroundColor: match.team_b_color + '20' }">
                        <span class="size-4 shrink-0 rounded-sm" :style="{ backgroundColor: match.team_b_color }"></span>
                        <span class="flex-1 text-sm font-bold">{{ match.team_b_name }}</span>
                    </div>
                    <div class="divide-y divide-border/50">
                        <div
                            v-for="stat in teamBStats"
                            :key="stat.id"
                            class="flex items-center gap-3 bg-card px-4 py-2.5"
                        >
                            <div
                                class="flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                :style="{ backgroundColor: match.team_b_color + '30' }"
                            >
                                {{ stat.jerseyNumber ?? stat.name.charAt(0).toUpperCase() }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <Link :href="`/clubs/${club.ulid}/players/${stat.ulid}`" class="block truncate text-sm font-medium hover:text-primary hover:underline">{{ stat.name }}</Link>
                                <div class="flex flex-wrap gap-3 text-xs text-muted-foreground">
                                    <span v-if="stat.goals" class="flex items-center gap-1">
                                        <CircleDot class="size-3 text-emerald-400" /> {{ stat.goals }} {{ stat.goals === 1 ? 'gol' : 'goles' }}
                                    </span>
                                    <span v-if="stat.assists">{{ stat.assists }} {{ stat.assists === 1 ? 'asist.' : 'asist.' }}</span>
                                    <span v-if="stat.yellowCards" class="flex items-center gap-1">
                                        <RectangleVertical class="size-3 text-yellow-400" /> {{ stat.yellowCards }}
                                    </span>
                                    <span v-if="stat.redCards" class="flex items-center gap-1">
                                        <RectangleVertical class="size-3 text-red-400" /> {{ stat.redCards }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== EDIT EVENTS (admin) ===== -->
            <div v-if="isAdmin" class="mt-6">
                <button
                    class="flex w-full items-center justify-between rounded-xl border border-border bg-card px-4 py-3 text-sm font-semibold transition-colors hover:bg-accent"
                    @click="showEditEvents = !showEditEvents"
                >
                    <span class="flex items-center gap-2">
                        <Pencil class="size-4 text-muted-foreground" />
                        Editar eventos
                    </span>
                    <component :is="showEditEvents ? ChevronUp : ChevronDown" class="size-4 text-muted-foreground" />
                </button>

                <div v-if="showEditEvents" class="mt-3 rounded-xl border border-border bg-card p-4">
                    <!-- Player selection -->
                    <p class="mb-2 text-[10px] font-bold tracking-widest text-muted-foreground uppercase">Jugador</p>
                    <div class="mb-3 grid grid-cols-2 gap-1.5">
                        <button
                            v-for="att in confirmedPlayers"
                            :key="att.id"
                            class="flex items-center gap-2 rounded-lg border px-2.5 py-2 text-left text-sm transition-all active:scale-[0.97]"
                            :class="editSelectedPlayerId === att.player_id
                                ? 'border-primary bg-primary/15 ring-2 ring-primary/40'
                                : 'border-border bg-accent/50 hover:bg-accent'"
                            @click="selectEditPlayer(att.player_id, att.player?.display_name ?? '')"
                        >
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold text-white"
                                :style="{ backgroundColor: att.team ? teamColor(att.team as 'a' | 'b') : (editSelectedPlayerId === att.player_id ? undefined : '#6b7280') }"
                                :class="!att.team && editSelectedPlayerId === att.player_id ? 'bg-primary text-primary-foreground' : ''"
                            >{{ att.player?.jersey_number ?? att.player?.display_name?.charAt(0) }}</span>
                            <span class="min-w-0 truncate">{{ att.player?.display_name }}</span>
                        </button>
                    </div>

                    <!-- Minute -->
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-[10px] font-bold tracking-widest text-muted-foreground uppercase">Minuto</p>
                        <div class="flex items-center gap-1">
                            <button
                                class="flex size-8 items-center justify-center rounded-lg border border-border bg-accent/50 transition-colors hover:bg-accent active:scale-95"
                                @click="editMinute = Math.max(0, editMinute - 1)"
                            >
                                <Minus class="size-3.5" />
                            </button>
                            <span class="w-10 text-center text-sm font-bold tabular-nums">{{ editMinute }}'</span>
                            <button
                                class="flex size-8 items-center justify-center rounded-lg border border-border bg-accent/50 transition-colors hover:bg-accent active:scale-95"
                                @click="editMinute = Math.min(200, editMinute + 1)"
                            >
                                <Plus class="size-3.5" />
                            </button>
                        </div>
                    </div>

                    <!-- Event type buttons -->
                    <div class="grid grid-cols-4 gap-1.5">
                        <button
                            v-for="et in editEventTypes"
                            :key="et.value"
                            :disabled="!editSelectedPlayerId || editSubmitting"
                            class="flex flex-col items-center justify-center gap-1 rounded-xl border p-2.5 transition-all active:scale-95 disabled:pointer-events-none disabled:opacity-30"
                            :class="et.bg"
                            @click="addEvent(et.value)"
                        >
                            <component :is="et.icon" class="size-5" :class="et.color" />
                            <span class="whitespace-pre-line text-center text-[10px] font-semibold leading-tight" :class="et.color">{{ et.label }}</span>
                        </button>
                    </div>

                    <p v-if="!editSelectedPlayerId" class="mt-2 text-center text-[10px] text-muted-foreground">
                        Selecciona un jugador para agregar un evento
                    </p>
                </div>
            </div>

            <!-- ===== MANAGE PLAYERS (admin) ===== -->
            <div v-if="isAdmin" class="mt-3">
                <button
                    class="flex w-full items-center justify-between rounded-xl border border-border bg-card px-4 py-3 text-sm font-semibold transition-colors hover:bg-accent"
                    @click="showManagePlayers = !showManagePlayers"
                >
                    <span class="flex items-center gap-2">
                        <Users class="size-4 text-muted-foreground" />
                        Gestionar jugadores ({{ confirmedPlayers.length }})
                    </span>
                    <component :is="showManagePlayers ? ChevronUp : ChevronDown" class="size-4 text-muted-foreground" />
                </button>

                <div v-if="showManagePlayers" class="mt-3 rounded-xl border border-border bg-card p-4">
                    <!-- Registered players -->
                    <p class="mb-2 text-[10px] font-bold tracking-widest text-muted-foreground uppercase">
                        Jugadores registrados ({{ confirmedPlayers.length }})
                    </p>
                    <div v-if="confirmedPlayers.length" class="mb-4 max-h-64 space-y-1.5 overflow-y-auto">
                        <div
                            v-for="att in confirmedPlayers"
                            :key="att.id"
                            class="flex items-center gap-2 rounded-lg border border-border bg-accent/50 px-2.5 py-2"
                        >
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold text-white"
                                :style="{ backgroundColor: att.team ? teamColor(att.team as 'a' | 'b') : '#6b7280' }"
                            >{{ att.player?.jersey_number ?? att.player?.display_name?.charAt(0) }}</span>
                            <div class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ att.player?.display_name }}</span>
                                <span v-if="att.team" class="text-[10px] text-muted-foreground">
                                    {{ att.team === 'a' ? match.team_a_name : match.team_b_name }}
                                </span>
                            </div>
                            <button
                                :disabled="removingAttendanceUlid === att.ulid"
                                class="shrink-0 rounded-md p-1 text-destructive/60 transition-colors hover:bg-destructive/10 hover:text-destructive disabled:opacity-50"
                                @click="removePlayerFromMatch(att.ulid)"
                            >
                                <UserMinus class="size-4" />
                            </button>
                        </div>
                    </div>
                    <p v-else class="mb-4 text-center text-xs text-muted-foreground">
                        No hay jugadores registrados.
                    </p>

                    <!-- Add players -->
                    <p class="mb-2 text-[10px] font-bold tracking-widest text-muted-foreground uppercase">
                        Agregar jugadores
                    </p>
                    <div class="relative mb-2">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <Search class="size-4 text-muted-foreground" />
                        </div>
                        <Input
                            v-model="playerSearchQuery"
                            placeholder="Buscar jugador..."
                            class="pl-9 text-sm"
                        />
                    </div>
                    <div v-if="filteredUnregisteredPlayers.length" class="max-h-64 divide-y divide-border/50 overflow-y-auto rounded-lg border border-border">
                        <div class="sticky top-0 z-10 flex items-center justify-end gap-3 border-b border-border bg-card px-3 py-1.5 text-[10px] font-medium text-muted-foreground">
                            <span class="flex items-center gap-1.5">
                                <span class="size-3 rounded-full" :style="{ backgroundColor: match.team_a_color }"></span>
                                {{ match.team_a_name }}
                            </span>
                            <span class="flex items-center gap-1.5">
                                <span class="size-3 rounded-full" :style="{ backgroundColor: match.team_b_color }"></span>
                                {{ match.team_b_name }}
                            </span>
                        </div>
                        <div
                            v-for="player in filteredUnregisteredPlayers"
                            :key="player.id"
                            class="flex items-center gap-2 px-1 py-2"
                        >
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full bg-muted text-[10px] font-bold text-muted-foreground"
                            >{{ player.jersey_number ?? player.display_name.charAt(0) }}</span>
                            <div class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ player.display_name }}</span>
                                <span v-if="player.position_label || player.jersey_number" class="text-[10px] text-muted-foreground">
                                    {{ [player.position_label, player.jersey_number ? `#${player.jersey_number}` : ''].filter(Boolean).join(' · ') }}
                                </span>
                            </div>
                            <div class="flex shrink-0 gap-1">
                                <button
                                    :disabled="addingPlayerId === player.id"
                                    :title="`Agregar a ${match.team_a_name}`"
                                    class="flex size-7 items-center justify-center rounded-full text-white shadow-sm transition-all active:scale-90 disabled:opacity-40"
                                    :style="{ backgroundColor: match.team_a_color }"
                                    @click="addPlayerToMatch(player.id, 'a')"
                                >
                                    <Plus class="size-3.5" />
                                </button>
                                <button
                                    :disabled="addingPlayerId === player.id"
                                    :title="`Agregar a ${match.team_b_name}`"
                                    class="flex size-7 items-center justify-center rounded-full text-white shadow-sm transition-all active:scale-90 disabled:opacity-40"
                                    :style="{ backgroundColor: match.team_b_color }"
                                    @click="addPlayerToMatch(player.id, 'b')"
                                >
                                    <Plus class="size-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-center text-xs text-muted-foreground">
                        {{ playerSearchQuery ? 'No se encontraron jugadores.' : 'Todos los jugadores activos ya están registrados.' }}
                    </p>
                </div>
            </div>

            <!-- ===== ADMIN ACTIONS ===== -->
            <div v-if="isAdmin" class="mt-6 space-y-2">
                <Button
                    v-if="match.status === 'completed'"
                    class="w-full gap-2"
                    @click="finalizeStats"
                >
                    <Star class="size-4" />
                    {{ match.stats_finalized_at ? 'Re-registrar estadisticas' : 'Registrar estadisticas' }}
                </Button>

                <Dialog v-model:open="showDeleteDialog">
                    <DialogTrigger as-child>
                        <Button variant="destructive" class="w-full gap-2">
                            <Trash2 class="size-4" />
                            Eliminar partido
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Eliminar partido</DialogTitle>
                            <DialogDescription>
                                Esta accion no se puede deshacer. Se eliminara el partido
                                <strong>"{{ match.title }}"</strong> junto con toda su informacion
                                de asistencia y eventos.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter class="gap-2 sm:gap-0">
                            <DialogClose as-child>
                                <Button variant="outline">Cancelar</Button>
                            </DialogClose>
                            <Button variant="destructive" class="gap-2" @click="deleteMatch">
                                <Trash2 class="size-4" />
                                Eliminar partido
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>

            <!-- Public link -->
            <div v-if="match.share_token" class="mt-4 text-center">
                <Link :href="`/match/${match.share_token}`" class="text-sm text-muted-foreground hover:underline">
                    Ver pagina publica del partido
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
