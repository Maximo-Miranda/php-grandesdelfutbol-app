<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeftRight,
    Check,
    CircleDot,
    Minus,
    Plus,
    RectangleVertical,
    RefreshCw,
    Shield,
    Shuffle,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, FootballMatch, MatchEvent, Player } from '@/types';

type Props = { club: Club; match: FootballMatch; players: Player[] };
const props = defineProps<Props>();

const base = `/clubs/${props.club.ulid}/matches`;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
    { title: 'Partidos', href: base },
    { title: props.match.title, href: `${base}/${props.match.ulid}` },
    { title: 'Live', href: `${base}/${props.match.ulid}/live` },
];

const primaryEventTypes = [
    { value: 'goal', label: 'Gol', icon: CircleDot, color: 'text-emerald-400', bg: 'bg-emerald-500/10 border-emerald-500/30 hover:bg-emerald-500/20' },
    { value: 'assist', label: 'Asist.', icon: CircleDot, color: 'text-sky-400', bg: 'bg-sky-500/10 border-sky-500/30 hover:bg-sky-500/20' },
    { value: 'yellow_card', label: 'Amarilla', icon: RectangleVertical, color: 'text-yellow-400', bg: 'bg-yellow-500/10 border-yellow-500/30 hover:bg-yellow-500/20' },
    { value: 'red_card', label: 'Roja', icon: RectangleVertical, color: 'text-red-400', bg: 'bg-red-500/10 border-red-500/30 hover:bg-red-500/20' },
];

const secondaryEventTypes = [
    { value: 'own_goal', label: 'Autogol', icon: Shield, color: 'text-orange-400', bg: 'bg-orange-500/10 border-orange-500/30 hover:bg-orange-500/20' },
    { value: 'penalty_scored', label: 'Penal', icon: CircleDot, color: 'text-emerald-300', bg: 'bg-emerald-500/10 border-emerald-500/30 hover:bg-emerald-500/20' },
    { value: 'penalty_missed', label: 'Penal\nfallado', icon: CircleDot, color: 'text-zinc-400', bg: 'bg-zinc-500/10 border-zinc-500/30 hover:bg-zinc-500/20' },
    { value: 'foul', label: 'Falta', icon: X, color: 'text-amber-400', bg: 'bg-amber-500/10 border-amber-500/30 hover:bg-amber-500/20' },
    { value: 'substitution', label: 'Cambio', icon: ArrowLeftRight, color: 'text-blue-400', bg: 'bg-blue-500/10 border-blue-500/30 hover:bg-blue-500/20' },
    { value: 'injury', label: 'Lesión', icon: AlertTriangle, color: 'text-rose-400', bg: 'bg-rose-500/10 border-rose-500/30 hover:bg-rose-500/20' },
    { value: 'save', label: 'Atajada', icon: Shield, color: 'text-violet-400', bg: 'bg-violet-500/10 border-violet-500/30 hover:bg-violet-500/20' },
    { value: 'free_kick', label: 'Tiro libre', icon: CircleDot, color: 'text-cyan-400', bg: 'bg-cyan-500/10 border-cyan-500/30 hover:bg-cyan-500/20' },
];

const selectedPlayerId = ref<number | null>(null);
const selectedPlayerName = ref('');
const autoMinute = ref(0);
const minute = ref(0);
const manualMode = ref(false);
const submitting = ref(false);
const lastRecorded = ref<{ player: string; event: string; minute: number } | null>(null);
const confirmingDeleteId = ref<string | null>(null);
let confirmTimeout: ReturnType<typeof setTimeout> | null = null;
let deleteTimeout: ReturnType<typeof setTimeout> | null = null;

// Running clock
const clockDisplay = ref('00:00');
const clockTimer = ref<ReturnType<typeof setInterval> | null>(null);

function updateClock() {
    if (props.match.started_at) {
        const started = new Date(props.match.started_at).getTime();
        const elapsed = Math.max(0, Date.now() - started);
        const totalSeconds = Math.floor(elapsed / 1000);
        const mins = Math.floor(totalSeconds / 60);
        const secs = totalSeconds % 60;
        clockDisplay.value = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        autoMinute.value = mins;
        if (!manualMode.value) {
            minute.value = mins;
        }
    }
}

function toggleManualMode() {
    if (manualMode.value) {
        manualMode.value = false;
        minute.value = autoMinute.value;
    } else {
        manualMode.value = true;
    }
}

function adjustMinute(delta: number) {
    minute.value = Math.max(0, Math.min(200, minute.value + delta));
}

usePoll(10000);

onMounted(() => {
    if (props.match.status === 'in_progress' && props.match.started_at) {
        updateClock();
        clockTimer.value = setInterval(updateClock, 1000);
    }
});

onUnmounted(() => {
    if (clockTimer.value) clearInterval(clockTimer.value);
    if (confirmTimeout) clearTimeout(confirmTimeout);
    if (deleteTimeout) clearTimeout(deleteTimeout);
});

const teamAPlayers = computed(() =>
    props.match.attendances?.filter(a => a.team === 'a') ?? [],
);

const teamBPlayers = computed(() =>
    props.match.attendances?.filter(a => a.team === 'b') ?? [],
);

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

const sortedEvents = computed(() =>
    [...(props.match.events ?? [])].sort((a, b) => b.minute - a.minute),
);

const statusConfig: Record<string, { label: string; class: string }> = {
    upcoming: { label: 'PROXIMO', class: 'bg-zinc-500/20 text-zinc-300 border-zinc-500/30' },
    in_progress: { label: 'EN JUEGO', class: 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30 animate-pulse' },
    completed: { label: 'FINALIZADO', class: 'bg-blue-500/20 text-blue-300 border-blue-500/30' },
    cancelled: { label: 'CANCELADO', class: 'bg-red-500/20 text-red-300 border-red-500/30' },
};

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

const eventIcon: Record<string, { color: string }> = {
    goal: { color: 'text-emerald-400' },
    assist: { color: 'text-sky-400' },
    yellow_card: { color: 'text-yellow-400' },
    red_card: { color: 'text-red-400' },
    penalty_scored: { color: 'text-emerald-300' },
    penalty_missed: { color: 'text-zinc-400' },
    own_goal: { color: 'text-orange-400' },
    save: { color: 'text-violet-400' },
    free_kick: { color: 'text-cyan-400' },
    substitution: { color: 'text-blue-400' },
    injury: { color: 'text-rose-400' },
    foul: { color: 'text-amber-400' },
};

function selectPlayer(playerId: number, playerName: string) {
    if (selectedPlayerId.value === playerId) {
        selectedPlayerId.value = null;
        selectedPlayerName.value = '';
    } else {
        selectedPlayerId.value = playerId;
        selectedPlayerName.value = playerName;
    }
}

function recordEvent(eventType: string) {
    if (!selectedPlayerId.value || submitting.value) return;
    submitting.value = true;

    const playerName = selectedPlayerName.value;
    const eventName = eventLabel[eventType] ?? eventType;
    const recordedMinute = minute.value;

    router.post(`${base}/${props.match.ulid}/events`, {
        player_id: selectedPlayerId.value,
        event_type: eventType,
        minute: recordedMinute,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            lastRecorded.value = { player: playerName, event: eventName, minute: recordedMinute };
            selectedPlayerId.value = null;
            selectedPlayerName.value = '';
            manualMode.value = false;
            minute.value = autoMinute.value;
            submitting.value = false;
            if (confirmTimeout) clearTimeout(confirmTimeout);
            confirmTimeout = setTimeout(() => { lastRecorded.value = null; }, 2500);
        },
        onError: () => { submitting.value = false; },
    });
}

function confirmRemoveEvent(eventUlid: string) {
    if (confirmingDeleteId.value === eventUlid) {
        if (deleteTimeout) clearTimeout(deleteTimeout);
        confirmingDeleteId.value = null;
        router.delete(`${base}/${props.match.ulid}/events/${eventUlid}`, { preserveScroll: true });
    } else {
        confirmingDeleteId.value = eventUlid;
        if (deleteTimeout) clearTimeout(deleteTimeout);
        deleteTimeout = setTimeout(() => { confirmingDeleteId.value = null; }, 3000);
    }
}

function autoAssignTeams() {
    router.post(`${base}/${props.match.ulid}/auto-assign`, {}, { preserveScroll: true });
}

function completeMatch() {
    router.post(`${base}/${props.match.ulid}/complete`);
}

function getPlayerTeam(playerId: number): 'a' | 'b' | null {
    const att = props.match.attendances?.find(a => a.player_id === playerId);
    return att?.team ?? null;
}

const allEventTypes = [...primaryEventTypes, ...secondaryEventTypes];
</script>

<template>
    <Head :title="`Live: ${match.title}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-3 py-4 sm:px-4 sm:py-6">
            <!-- Header -->
            <div class="mb-3 flex items-center justify-end">
                <RefreshCw class="size-4 text-muted-foreground animate-spin" style="animation-duration: 10s" />
            </div>

            <!-- Scoreboard -->
            <div class="relative mb-5 overflow-hidden rounded-2xl bg-gradient-to-b from-zinc-900 via-zinc-900 to-zinc-950 p-5 shadow-lg dark:from-zinc-900/80 dark:to-black/60">
                <div class="pointer-events-none absolute inset-0 opacity-[0.03]">
                    <div class="absolute top-1/2 left-1/2 size-32 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white"></div>
                    <div class="absolute inset-y-0 left-1/2 w-px bg-white"></div>
                </div>

                <div class="relative text-center">
                    <span
                        class="inline-block rounded-full border px-3 py-0.5 text-[10px] font-bold tracking-widest uppercase"
                        :class="statusConfig[match.status]?.class ?? 'bg-zinc-500/20 text-zinc-300'"
                    >
                        {{ statusConfig[match.status]?.label ?? match.status }}
                    </span>

                    <p class="mt-2 text-sm font-medium text-zinc-400">{{ match.title }}</p>

                    <!-- Score -->
                    <div class="mt-4 flex items-center justify-center gap-4 sm:gap-8">
                        <div class="min-w-0 flex-1 text-right">
                            <p class="truncate text-xs font-bold tracking-wider text-zinc-400 uppercase sm:text-sm">{{ match.team_a_name }}</p>
                            <p class="text-5xl font-black tabular-nums text-white sm:text-6xl">{{ teamAGoals }}</p>
                        </div>

                        <div class="flex flex-col items-center">
                            <span class="text-xl font-light text-zinc-600 select-none">vs</span>
                        </div>

                        <div class="min-w-0 flex-1 text-left">
                            <p class="truncate text-xs font-bold tracking-wider text-zinc-400 uppercase sm:text-sm">{{ match.team_b_name }}</p>
                            <p class="text-5xl font-black tabular-nums text-white sm:text-6xl">{{ teamBGoals }}</p>
                        </div>
                    </div>

                    <!-- Running clock -->
                    <div v-if="match.status === 'in_progress'" class="mt-3">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/20 px-4 py-1.5 text-base font-bold tabular-nums tracking-wider text-emerald-400 font-mono">
                            <span class="size-2 animate-pulse rounded-full bg-emerald-400"></span>
                            {{ clockDisplay }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Bar -->
            <div v-if="match.status === 'in_progress'" class="mb-5 flex gap-2">
                <Button variant="outline" size="sm" class="flex-1" @click="autoAssignTeams">
                    <Shuffle class="mr-1.5 size-3.5" />Sortear
                </Button>
                <Button variant="outline" size="sm" class="flex-1" @click="completeMatch">
                    <Check class="mr-1.5 size-3.5" />Terminar
                </Button>
            </div>

            <!-- ===== EVENT REGISTRATION ===== -->
            <div class="mb-5">
                <!-- Success confirmation toast -->
                <Transition
                    enter-active-class="transition-all duration-300 ease-out"
                    enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition-all duration-200 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-2"
                >
                    <div v-if="lastRecorded" class="mb-3 flex items-center gap-2 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-2.5 text-sm text-emerald-400">
                        <Check class="size-4 shrink-0" />
                        <span class="truncate"><strong>{{ lastRecorded.player }}</strong> &mdash; {{ lastRecorded.event }} ({{ lastRecorded.minute }}')</span>
                    </div>
                </Transition>

                <!-- Selected player indicator -->
                <div v-if="selectedPlayerId" class="mb-3 flex items-center justify-between rounded-lg border border-primary/30 bg-primary/10 px-3 py-2">
                    <span class="text-sm font-semibold text-primary">{{ selectedPlayerName }}</span>
                    <button class="text-primary/60 hover:text-primary" @click="selectedPlayerId = null; selectedPlayerName = ''">
                        <X class="size-4" />
                    </button>
                </div>

                <h3 class="mb-2 text-[10px] font-bold tracking-widest text-muted-foreground uppercase">
                    1. Selecciona jugador
                </h3>

                <!-- Teams side by side -->
                <div class="grid grid-cols-2 gap-2">
                    <!-- Team A -->
                    <div>
                        <p class="mb-1.5 text-center text-xs font-bold tracking-wide text-zinc-400 uppercase">{{ match.team_a_name }}</p>
                        <div class="space-y-1">
                            <button
                                v-for="att in teamAPlayers"
                                :key="att.id"
                                class="flex w-full items-center gap-2 rounded-lg border px-2.5 py-2.5 text-left transition-all active:scale-[0.97]"
                                :class="selectedPlayerId === att.player_id
                                    ? 'border-primary bg-primary/15 ring-2 ring-primary/40 shadow-sm shadow-primary/20'
                                    : 'border-border bg-accent/50 hover:bg-accent'"
                                @click="selectPlayer(att.player_id, att.player?.display_name ?? '')"
                            >
                                <span
                                    class="flex size-7 shrink-0 items-center justify-center rounded-full text-[11px] font-bold"
                                    :class="selectedPlayerId === att.player_id ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'"
                                >{{ att.player?.display_name?.charAt(0) }}</span>
                                <span class="min-w-0 truncate text-xs font-medium sm:text-sm">{{ att.player?.display_name }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Team B -->
                    <div>
                        <p class="mb-1.5 text-center text-xs font-bold tracking-wide text-zinc-400 uppercase">{{ match.team_b_name }}</p>
                        <div class="space-y-1">
                            <button
                                v-for="att in teamBPlayers"
                                :key="att.id"
                                class="flex w-full items-center gap-2 rounded-lg border px-2.5 py-2.5 text-left transition-all active:scale-[0.97]"
                                :class="selectedPlayerId === att.player_id
                                    ? 'border-primary bg-primary/15 ring-2 ring-primary/40 shadow-sm shadow-primary/20'
                                    : 'border-border bg-accent/50 hover:bg-accent'"
                                @click="selectPlayer(att.player_id, att.player?.display_name ?? '')"
                            >
                                <span
                                    class="flex size-7 shrink-0 items-center justify-center rounded-full text-[11px] font-bold"
                                    :class="selectedPlayerId === att.player_id ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'"
                                >{{ att.player?.display_name?.charAt(0) }}</span>
                                <span class="min-w-0 truncate text-xs font-medium sm:text-sm">{{ att.player?.display_name }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Minute + Event Type (step 2) -->
                <div class="mt-4">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="text-[10px] font-bold tracking-widest text-muted-foreground uppercase">
                            2. Toca evento
                        </h3>

                        <!-- Minute display: tap to toggle manual -->
                        <div class="flex items-center gap-1">
                            <button
                                v-if="manualMode"
                                class="flex size-9 items-center justify-center rounded-lg border border-border bg-accent/50 transition-colors hover:bg-accent active:scale-95"
                                @click="adjustMinute(-1)"
                            >
                                <Minus class="size-4" />
                            </button>

                            <button
                                class="rounded-lg px-3 py-1 text-sm font-bold tabular-nums transition-all active:scale-95"
                                :class="manualMode
                                    ? 'border-2 border-amber-400/50 bg-amber-500/15 text-amber-400'
                                    : 'border border-border bg-accent/50 text-muted-foreground'"
                                @click="toggleManualMode"
                            >
                                {{ minute }}'
                                <span v-if="!manualMode" class="ml-1 text-[9px] font-normal opacity-60">editar</span>
                                <span v-else class="ml-1 text-[9px] font-normal opacity-60">auto</span>
                            </button>

                            <button
                                v-if="manualMode"
                                class="flex size-9 items-center justify-center rounded-lg border border-border bg-accent/50 transition-colors hover:bg-accent active:scale-95"
                                @click="adjustMinute(1)"
                            >
                                <Plus class="size-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Event type buttons -->
                    <div class="grid grid-cols-4 gap-1.5">
                        <button
                            v-for="et in allEventTypes"
                            :key="et.value"
                            :disabled="!selectedPlayerId || submitting"
                            class="flex flex-col items-center justify-center gap-1.5 rounded-xl border p-3 transition-all active:scale-95 disabled:opacity-30 disabled:pointer-events-none sm:p-3.5"
                            :class="et.bg"
                            @click="recordEvent(et.value)"
                        >
                            <component :is="et.icon" class="size-6 sm:size-7" :class="et.color" />
                            <span class="whitespace-pre-line text-center text-[10px] font-semibold leading-tight sm:text-xs" :class="et.color">{{ et.label }}</span>
                        </button>
                    </div>

                    <!-- Hint when no player selected -->
                    <p v-if="!selectedPlayerId" class="mt-1 text-center text-[10px] text-muted-foreground">
                        Toca un jugador arriba para registrar un evento
                    </p>
                </div>
            </div>

            <!-- ===== EVENTS TIMELINE ===== -->
            <div>
                <h3 class="mb-3 text-[10px] font-bold tracking-widest text-muted-foreground uppercase">
                    Eventos ({{ match.events?.length ?? 0 }})
                </h3>

                <div v-if="sortedEvents.length" class="relative space-y-0">
                    <div class="absolute top-0 bottom-0 left-[18px] w-px bg-border"></div>

                    <div
                        v-for="event in sortedEvents"
                        :key="event.id"
                        class="group relative flex items-center gap-3 py-1.5"
                    >
                        <!-- Minute bubble -->
                        <span class="z-10 flex size-9 shrink-0 items-center justify-center rounded-full border border-border bg-card text-xs font-bold tabular-nums">
                            {{ event.minute }}'
                        </span>

                        <!-- Event card -->
                        <div class="flex min-w-0 flex-1 items-center gap-2 rounded-lg border border-border bg-card/50 px-3 py-2">
                            <CircleDot v-if="['goal', 'assist', 'penalty_scored', 'penalty_missed', 'free_kick'].includes(event.event_type)" class="size-3.5 shrink-0" :class="eventIcon[event.event_type]?.color ?? 'text-muted-foreground'" />
                            <RectangleVertical v-else-if="event.event_type === 'yellow_card' || event.event_type === 'red_card'" class="size-3.5 shrink-0" :class="eventIcon[event.event_type]?.color ?? 'text-muted-foreground'" />
                            <ArrowLeftRight v-else-if="event.event_type === 'substitution'" class="size-3.5 shrink-0" :class="eventIcon[event.event_type]?.color" />
                            <AlertTriangle v-else-if="event.event_type === 'injury'" class="size-3.5 shrink-0" :class="eventIcon[event.event_type]?.color" />
                            <X v-else-if="event.event_type === 'foul'" class="size-3.5 shrink-0" :class="eventIcon[event.event_type]?.color" />
                            <Shield v-else class="size-3.5 shrink-0" :class="eventIcon[event.event_type]?.color ?? 'text-muted-foreground'" />

                            <div class="min-w-0 flex-1">
                                <Link
                                    v-if="event.player"
                                    :href="`/clubs/${club.ulid}/players/${event.player.ulid}`"
                                    class="block truncate text-sm font-medium hover:text-primary hover:underline"
                                >{{ event.player.display_name }}</Link>
                                <p class="text-[10px] text-muted-foreground">{{ eventLabel[event.event_type] ?? event.event_type }}</p>
                            </div>

                            <Badge
                                v-if="getPlayerTeam(event.player_id)"
                                variant="outline"
                                class="shrink-0 text-[9px] px-1.5"
                            >
                                {{ getPlayerTeam(event.player_id) === 'a' ? match.team_a_name : match.team_b_name }}
                            </Badge>

                            <button
                                class="shrink-0 rounded-md p-1 transition-colors"
                                :class="confirmingDeleteId === event.ulid
                                    ? 'bg-destructive/20 text-destructive'
                                    : 'text-destructive/60 hover:bg-destructive/10 hover:text-destructive'"
                                @click="confirmRemoveEvent(event.ulid)"
                            >
                                <Trash2 v-if="confirmingDeleteId !== event.id" class="size-4" />
                                <Check v-else class="size-4" />
                            </button>
                        </div>
                    </div>
                </div>
                <p v-else class="text-center text-sm text-muted-foreground">No hay eventos registrados.</p>
            </div>
        </div>
    </AppLayout>
</template>
