<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import {
    Check,
    Maximize,
    Minimize,
    Pencil,
    Share2,
    Shuffle,
    Trash2,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import EventTimeline from '@/components/match/EventTimeline.vue';
import EventTypeGrid from '@/components/match/EventTypeGrid.vue';
import LiveScoreboard from '@/components/match/LiveScoreboard.vue';
import MinuteSecondInput from '@/components/match/MinuteSecondInput.vue';
import PlayerSelector from '@/components/match/PlayerSelector.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    ALL_EVENT_TYPES,
    DEFAULT_PANEL_COLOR,
    EVENT_LABELS,
    EVENT_PANEL_COLORS,
    EVENT_SCOPES,
    allowsOptionalTeam,
    countTeamGoals as countTeamGoalsUtil,
} from '@/lib/match-events';
import { formatEventTime } from '@/lib/utils';
import type { BreadcrumbItem, Club, FootballMatch, Player } from '@/types';

type Props = { club: Club; match: FootballMatch; players: Player[] };
const props = defineProps<Props>();

const base = `/clubs/${props.club.ulid}/matches`;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubes', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
    { title: 'Partidos', href: base },
    { title: `${props.match.title} — Live`, href: `${base}/${props.match.ulid}/live` },
];

function panelColor() {
    return EVENT_PANEL_COLORS[pendingEventType.value ?? ''] ?? DEFAULT_PANEL_COLOR;
}

// --- State ---
const selectedPlayerId = ref<number | null>(null);
const selectedPlayerName = ref('');
const pendingEventType = ref<string | null>(null);
const pendingRequiresPlayer = ref(false);
// Substitution flow: step 1 = player out selected, waiting for player in
const subOutPlayerId = ref<number | null>(null);
const subOutPlayerName = ref('');
const autoMinute = ref(0);
const autoSecond = ref(0);
const minute = ref(0);
const second = ref(0);
const manualMode = ref(false);
const submitting = ref(false);
const lastRecorded = ref<{ player: string; event: string; minute: number; second: number } | null>(null);
const deletingEventUlid = ref<string | null>(null);
const deletingEventLabel = ref('');
const showDeleteEventDialog = ref(false);
const recentPlayerIds = ref<number[]>([]);
const isFullscreen = ref(false);
let confirmTimeout: ReturnType<typeof setTimeout> | null = null;

// --- Clock ---
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
        autoSecond.value = secs;
        if (!manualMode.value) {
            minute.value = mins;
            second.value = secs;
        }
    }
}

function toggleManualMode() {
    if (manualMode.value) {
        manualMode.value = false;
        minute.value = autoMinute.value;
        second.value = autoSecond.value;
    } else {
        manualMode.value = true;
    }
}

// --- Fullscreen ---
async function toggleFullscreen() {
    if (!document.fullscreenElement) {
        try {
            await document.documentElement.requestFullscreen?.();
            // Try to lock to landscape on mobile/tablet
            await (screen.orientation as any)?.lock?.('landscape').catch(() => {});
        } catch { /* ignore */ }
        isFullscreen.value = true;
    } else {
        try {
            screen.orientation?.unlock?.();
            await document.exitFullscreen?.();
        } catch { /* ignore */ }
        isFullscreen.value = false;
    }
}

usePoll(10000);

onMounted(() => {
    if (props.match.status === 'in_progress' && props.match.started_at) {
        updateClock();
        clockTimer.value = setInterval(updateClock, 1000);
    }
    // Restore fullscreen preference
    const savedFs = localStorage.getItem('live-fullscreen');
    if (savedFs === 'true') {
        document.documentElement.requestFullscreen?.().catch(() => {});
        isFullscreen.value = true;
    }
    document.addEventListener('fullscreenchange', onFullscreenChange);
});

onUnmounted(() => {
    if (clockTimer.value) clearInterval(clockTimer.value);
    if (confirmTimeout) clearTimeout(confirmTimeout);
    document.removeEventListener('fullscreenchange', onFullscreenChange);
});

function onFullscreenChange() {
    isFullscreen.value = !!document.fullscreenElement;
    localStorage.setItem('live-fullscreen', String(isFullscreen.value));
    if (!isFullscreen.value) {
        screen.orientation?.unlock?.();
    }
}

// --- Computed ---
const teamAPlayers = computed(() => props.match.attendances?.filter(a => a.team === 'a') ?? []);
const teamBPlayers = computed(() => props.match.attendances?.filter(a => a.team === 'b') ?? []);

const teamAGoals = computed(() => countTeamGoalsUtil(props.match, 'a'));
const teamBGoals = computed(() => countTeamGoalsUtil(props.match, 'b'));

const sortedEvents = computed(() =>
    [...(props.match.events ?? [])].sort((a, b) => b.minute - a.minute || b.second - a.second),
);

// --- Unified event flow ---
function onEventSelected(eventType: string) {
    const scope = EVENT_SCOPES[eventType] ?? 'player';

    if (scope === 'neutral' && !allowsOptionalTeam(eventType)) {
        recordNeutralEvent(eventType);
        return;
    }

    pendingEventType.value = eventType;
    pendingRequiresPlayer.value = false;
}

function submitTeamOnlyEvent(eventType: string, team: 'a' | 'b') {
    if (submitting.value) return;
    submitting.value = true;

    const teamName = team === 'a' ? props.match.team_a_name : props.match.team_b_name;
    const eventName = EVENT_LABELS[eventType] ?? eventType;
    const recordedMinute = minute.value;
    const recordedSecond = second.value;

    navigator.vibrate?.(50);

    router.post(`${base}/${props.match.ulid}/events`, {
        event_type: eventType,
        team,
        minute: recordedMinute,
        second: recordedSecond,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            lastRecorded.value = { player: teamName, event: eventName, minute: recordedMinute, second: recordedSecond };
            pendingEventType.value = null;
            selectedPlayerId.value = null;
            selectedPlayerName.value = '';
            subOutPlayerId.value = null;
            subOutPlayerName.value = '';
            submitting.value = false;
            if (confirmTimeout) clearTimeout(confirmTimeout);
            confirmTimeout = setTimeout(() => { lastRecorded.value = null; }, 2500);
        },
        onError: () => { submitting.value = false; },
    });
}

function submitTimeoutNoTeam() {
    pendingEventType.value = null;
    recordNeutralEvent('timeout');
}


function onPlayerSelected(playerId: number, playerName: string) {
    // Substitution flow
    if (pendingEventType.value === 'substitution') {
        if (!subOutPlayerId.value) {
            subOutPlayerId.value = playerId;
            subOutPlayerName.value = playerName;
            return;
        }
        // Can't substitute with the same player
        if (playerId === subOutPlayerId.value) return;
        submitSubstitution(subOutPlayerId.value, subOutPlayerName.value, playerId, playerName);
        return;
    }

    if (selectedPlayerId.value === playerId) {
        selectedPlayerId.value = null;
        selectedPlayerName.value = '';
        return;
    }

    selectedPlayerId.value = playerId;
    selectedPlayerName.value = playerName;

    // If there's a pending event, auto-submit
    if (pendingEventType.value) {
        submitPlayerEvent(pendingEventType.value, playerId, playerName);
        pendingEventType.value = null;
    }
}

function submitSubstitution(outId: number, outName: string, inId: number, inName: string) {
    if (submitting.value) return;
    submitting.value = true;

    const recordedMinute = minute.value;
    const recordedSecond = second.value;

    addRecentPlayer(inId);
    navigator.vibrate?.(50);

    router.post(`${base}/${props.match.ulid}/events`, {
        player_id: outId,
        related_player_id: inId,
        event_type: 'substitution',
        minute: recordedMinute,
        second: recordedSecond,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            lastRecorded.value = { player: `${outName} → ${inName}`, event: 'Cambio', minute: recordedMinute, second: recordedSecond };
            resetSubstitution();
            submitting.value = false;
            if (confirmTimeout) clearTimeout(confirmTimeout);
            confirmTimeout = setTimeout(() => { lastRecorded.value = null; }, 2500);
        },
        onError: () => { submitting.value = false; },
    });
}

function resetSubstitution() {
    subOutPlayerId.value = null;
    subOutPlayerName.value = '';
    pendingEventType.value = null;
    selectedPlayerId.value = null;
    selectedPlayerName.value = '';
    manualMode.value = false;
    minute.value = autoMinute.value;
    second.value = autoSecond.value;
}

function submitPlayerEvent(eventType: string, playerId: number, playerName: string) {
    if (submitting.value) return;
    submitting.value = true;

    const eventName = EVENT_LABELS[eventType] ?? eventType;
    const recordedMinute = minute.value;
    const recordedSecond = second.value;

    // Add to recent players
    addRecentPlayer(playerId);

    // Haptic feedback
    navigator.vibrate?.(50);

    router.post(`${base}/${props.match.ulid}/events`, {
        player_id: playerId,
        event_type: eventType,
        minute: recordedMinute,
        second: recordedSecond,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            lastRecorded.value = { player: playerName, event: eventName, minute: recordedMinute, second: recordedSecond };
            selectedPlayerId.value = null;
            selectedPlayerName.value = '';
            pendingEventType.value = null;
            manualMode.value = false;
            minute.value = autoMinute.value;
            second.value = autoSecond.value;
            submitting.value = false;
            if (confirmTimeout) clearTimeout(confirmTimeout);
            confirmTimeout = setTimeout(() => { lastRecorded.value = null; }, 2500);
        },
        onError: () => { submitting.value = false; },
    });
}

function recordNeutralEvent(eventType: string) {
    if (submitting.value) return;
    submitting.value = true;

    const eventName = EVENT_LABELS[eventType] ?? eventType;
    const recordedMinute = minute.value;
    const recordedSecond = second.value;

    navigator.vibrate?.(50);

    router.post(`${base}/${props.match.ulid}/events`, {
        event_type: eventType,
        minute: recordedMinute,
        second: recordedSecond,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            lastRecorded.value = { player: 'Partido', event: eventName, minute: recordedMinute, second: recordedSecond };
            submitting.value = false;
            if (confirmTimeout) clearTimeout(confirmTimeout);
            confirmTimeout = setTimeout(() => { lastRecorded.value = null; }, 2500);
        },
        onError: () => { submitting.value = false; },
    });
}

function addRecentPlayer(playerId: number) {
    recentPlayerIds.value = [playerId, ...recentPlayerIds.value.filter(id => id !== playerId)].slice(0, 5);
}

function confirmRemoveEvent(eventUlid: string) {
    const event = props.match.events?.find(e => e.ulid === eventUlid);
    const label = event ? (EVENT_LABELS[event.event_type] ?? event.event_type) : '';
    const player = event?.player?.display_name;
    deletingEventUlid.value = eventUlid;
    deletingEventLabel.value = player ? `${label} — ${player}` : label;
    showDeleteEventDialog.value = true;
}

function executeDeleteEvent() {
    if (!deletingEventUlid.value) return;
    router.delete(`${base}/${props.match.ulid}/events/${deletingEventUlid.value}`, {
        preserveScroll: true,
        onFinish: () => {
            showDeleteEventDialog.value = false;
            deletingEventUlid.value = null;
        },
    });
}

function autoAssignTeams() {
    router.post(`${base}/${props.match.ulid}/auto-assign`, {}, { preserveScroll: true });
}

function completeMatch() {
    router.post(`${base}/${props.match.ulid}/complete`);
}

const linkCopied = ref(false);

async function shareMatch() {
    if (!props.match.share_token) return;
    const url = `${window.location.origin}/match/${props.match.share_token}`;

    // Try native share first (mobile)
    if (navigator.share) {
        try {
            await navigator.share({ title: props.match.title, url });
            return;
        } catch { /* user cancelled or not supported */ }
    }

    // Fallback: copy to clipboard
    await navigator.clipboard?.writeText(url);
    linkCopied.value = true;
    setTimeout(() => { linkCopied.value = false; }, 2000);
}

function cancelPendingEvent() {
    pendingEventType.value = null;
    pendingRequiresPlayer.value = false;
    subOutPlayerId.value = null;
    subOutPlayerName.value = '';
}

// --- Auto-scroll ---
const actionPanelRef = ref<HTMLElement | null>(null);
const eventGridRef = ref<HTMLElement | null>(null);

function smoothScrollTo(el: HTMLElement | null) {
    if (!el) return;
    const offset = 16;
    const top = el.getBoundingClientRect().top + window.scrollY - offset;
    window.scrollTo({ top, behavior: 'smooth' });
}

function scrollToAction() {
    nextTick(() => smoothScrollTo(actionPanelRef.value ?? eventGridRef.value));
}

function scrollToGrid() {
    nextTick(() => smoothScrollTo(eventGridRef.value));
}

watch(pendingEventType, (val) => {
    if (val) scrollToAction();
    else scrollToGrid();
});

watch(subOutPlayerId, (val) => {
    if (val) scrollToAction();
});
</script>

<template>
    <Head :title="`Live: ${match.title}`" />
    <component :is="isFullscreen ? 'div' : AppLayout" :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full px-3 py-3 sm:px-4 sm:py-6" :class="isFullscreen ? 'max-w-5xl' : 'max-w-2xl'">
            <!-- Scoreboard (sticky on mobile) -->
            <div class="sticky top-0 z-20 -mx-3 bg-background/95 px-3 pb-2 backdrop-blur-sm sm:static sm:mx-0 sm:px-0 sm:pb-0">
                <LiveScoreboard
                    :match="match"
                    :clock-display="clockDisplay"
                    :team-a-goals="teamAGoals"
                    :team-b-goals="teamBGoals"
                />

                <!-- Quick Actions -->
                <div v-if="match.status === 'in_progress'" class="mt-2 flex gap-2">
                    <template v-if="!isFullscreen">
                        <Button variant="outline" size="sm" class="flex-1 min-h-[44px]" @click="autoAssignTeams">
                            <Shuffle class="mr-1.5 size-3.5" />Sortear equipos
                        </Button>
                        <Button variant="outline" size="sm" class="flex-1 min-h-[44px]" @click="completeMatch">
                            <Check class="mr-1.5 size-3.5" />Terminar
                        </Button>
                        <Link :href="`${base}/${match.ulid}/edit`">
                            <Button variant="outline" size="sm" class="min-h-[44px]" title="Editar partido">
                                <Pencil class="size-3.5" />
                            </Button>
                        </Link>
                        <Button v-if="match.share_token" variant="outline" size="sm" class="min-h-[44px]" @click="shareMatch">
                            <Check v-if="linkCopied" class="size-3.5 text-emerald-400" />
                            <Share2 v-else class="size-3.5" />
                        </Button>
                    </template>
                    <Button variant="outline" size="sm" :class="isFullscreen ? 'flex-1 min-h-[44px]' : 'min-h-[44px]'" @click="toggleFullscreen">
                        <Maximize v-if="!isFullscreen" class="size-3.5" />
                        <Minimize v-else class="size-3.5" />
                        <span v-if="isFullscreen" class="ml-1.5">Salir de pantalla completa</span>
                    </Button>
                </div>
            </div>

            <!-- ===== EVENT REGISTRATION ===== -->
            <div class="mt-3 space-y-4">
                <!-- Step 1: Event type (sticky) -->
                <div class="sticky top-[calc(theme(spacing.0)+10rem)] z-10 -mx-3 bg-background/95 px-3 pb-2 backdrop-blur-sm sm:static sm:mx-0 sm:px-0 sm:pb-0">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 v-if="!pendingEventType" class="text-xs font-extrabold tracking-widest text-muted-foreground uppercase">
                            Registrar evento
                        </h3>
                        <div v-else></div>
                        <MinuteSecondInput
                            v-model:minute="minute"
                            v-model:second="second"
                            :manual-mode="manualMode"
                            @toggle-manual="toggleManualMode"
                        />
                    </div>

                    <!-- ===== PENDING EVENT: integrated panel ===== -->
                    <div v-if="pendingEventType" ref="actionPanelRef" class="mb-2">
                        <!-- Substitution flow -->
                        <div v-if="pendingEventType === 'substitution'" class="overflow-hidden rounded-xl border border-blue-500/30">
                            <div class="flex items-center justify-between bg-blue-500/10 px-4 py-3">
                                <div>
                                    <p class="text-sm font-bold text-blue-400">Cambio</p>
                                    <p v-if="!subOutPlayerId" class="text-xs text-blue-300/70">Toca el jugador que SALE</p>
                                    <p v-else class="text-xs text-blue-300/70"><strong>{{ subOutPlayerName }}</strong> sale &mdash; toca quien ENTRA</p>
                                </div>
                                <button
                                    class="rounded-lg border border-blue-500/30 px-3 py-1.5 text-xs font-medium text-blue-400 transition-colors hover:bg-blue-500/20"
                                    @click="cancelPendingEvent"
                                >
                                    Cancelar
                                </button>
                            </div>
                            <div class="bg-blue-500/5 p-3">
                                <PlayerSelector
                                    :team-a-players="teamAPlayers"
                                    :team-b-players="teamBPlayers"
                                    :team-a-name="match.team_a_name"
                                    :team-b-name="match.team_b_name"
                                    :team-a-color="match.team_a_color"
                                    :team-b-color="match.team_b_color"
                                    :selected-player-id="subOutPlayerId"
                                    :disabled-player-id="subOutPlayerId"
                                    :recent-player-ids="recentPlayerIds"
                                    hide-headers
                                    @select="onPlayerSelected"
                                />

                                <div v-if="!subOutPlayerId" class="mt-3">
                                    <div class="relative mb-3 flex items-center">
                                        <div class="flex-1 border-t border-border"></div>
                                        <span class="px-3 text-[10px] font-medium text-muted-foreground">o solo al equipo</span>
                                        <div class="flex-1 border-t border-border"></div>
                                    </div>
                                    <div class="flex gap-2">
                                        <button
                                            :disabled="submitting"
                                            class="flex min-h-[44px] flex-1 items-center justify-center gap-2 rounded-lg border border-border bg-card/80 text-sm font-medium transition-all active:scale-[0.97] hover:bg-accent disabled:opacity-30"
                                            @click="submitTeamOnlyEvent('substitution', 'a')"
                                        >
                                            <span class="size-3 rounded-sm" :style="{ backgroundColor: match.team_a_color ?? '#6b7280' }"></span>
                                            {{ match.team_a_name }}
                                        </button>
                                        <button
                                            :disabled="submitting"
                                            class="flex min-h-[44px] flex-1 items-center justify-center gap-2 rounded-lg border border-border bg-card/80 text-sm font-medium transition-all active:scale-[0.97] hover:bg-accent disabled:opacity-30"
                                            @click="submitTeamOnlyEvent('substitution', 'b')"
                                        >
                                            <span class="size-3 rounded-sm" :style="{ backgroundColor: match.team_b_color ?? '#6b7280' }"></span>
                                            {{ match.team_b_name }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Normal event: integrated panel -->
                        <div v-else class="overflow-hidden rounded-xl border" :class="panelColor().border">
                            <div class="flex items-center justify-between px-4 py-3" :class="panelColor().bg">
                                <div>
                                    <p class="text-sm font-bold" :class="panelColor().text">{{ EVENT_LABELS[pendingEventType] }}</p>
                                    <p class="text-xs" :class="panelColor().textLight">
                                        <template v-if="EVENT_SCOPES[pendingEventType] === 'player'">Toca un jugador o asigna al equipo</template>
                                        <template v-else-if="allowsOptionalTeam(pendingEventType)">Selecciona el equipo o registra sin equipo</template>
                                        <template v-else>Selecciona el equipo</template>
                                    </p>
                                </div>
                                <button
                                    class="rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors"
                                    :class="[panelColor().border, panelColor().text]"
                                    @click="cancelPendingEvent"
                                >
                                    Cancelar
                                </button>
                            </div>
                            <div class="p-3 space-y-3" :class="panelColor().bgLight">
                                <!-- Player selector (for player-scope events) -->
                                <template v-if="EVENT_SCOPES[pendingEventType] === 'player'">
                                    <PlayerSelector
                                        :team-a-players="teamAPlayers"
                                        :team-b-players="teamBPlayers"
                                        :team-a-name="match.team_a_name"
                                        :team-b-name="match.team_b_name"
                                        :team-a-color="match.team_a_color"
                                        :team-b-color="match.team_b_color"
                                        :selected-player-id="selectedPlayerId"
                                        :recent-player-ids="recentPlayerIds"
                                        hide-headers
                                        @select="onPlayerSelected"
                                    />
                                </template>

                                <!-- Team-only buttons -->
                                <div>
                                    <div v-if="EVENT_SCOPES[pendingEventType] === 'player'" class="relative mb-3 flex items-center">
                                        <div class="flex-1 border-t border-border"></div>
                                        <span class="px-3 text-[10px] font-medium text-muted-foreground">o solo al equipo</span>
                                        <div class="flex-1 border-t border-border"></div>
                                    </div>
                                    <div class="flex gap-2">
                                        <button
                                            :disabled="submitting"
                                            class="flex min-h-[44px] flex-1 items-center justify-center gap-2 rounded-lg border border-border bg-card/80 text-sm font-medium transition-all active:scale-[0.97] hover:bg-accent disabled:opacity-30"
                                            @click="submitTeamOnlyEvent(pendingEventType!, 'a')"
                                        >
                                            <span class="size-3 rounded-sm" :style="{ backgroundColor: match.team_a_color ?? '#6b7280' }"></span>
                                            {{ match.team_a_name }}
                                        </button>
                                        <button
                                            :disabled="submitting"
                                            class="flex min-h-[44px] flex-1 items-center justify-center gap-2 rounded-lg border border-border bg-card/80 text-sm font-medium transition-all active:scale-[0.97] hover:bg-accent disabled:opacity-30"
                                            @click="submitTeamOnlyEvent(pendingEventType!, 'b')"
                                        >
                                            <span class="size-3 rounded-sm" :style="{ backgroundColor: match.team_b_color ?? '#6b7280' }"></span>
                                            {{ match.team_b_name }}
                                        </button>
                                    </div>
                                    <button
                                        v-if="allowsOptionalTeam(pendingEventType)"
                                        :disabled="submitting"
                                        class="mt-2 flex min-h-[40px] w-full items-center justify-center rounded-lg border border-border bg-card/50 text-xs font-medium text-muted-foreground transition-all active:scale-[0.97] hover:bg-accent disabled:opacity-30"
                                        @click="submitTimeoutNoTeam"
                                    >
                                        Sin equipo
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== NO PENDING EVENT: show event grid ===== -->
                    <template v-else>
                        <div ref="eventGridRef"></div>
                        <EventTypeGrid
                            :events="ALL_EVENT_TYPES"
                            :disabled="submitting"
                            :cols="4"
                            @select="onEventSelected"
                        />
                    </template>
                </div>

            </div>

            <!-- Success Toast -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <div v-if="lastRecorded" class="mt-3 flex items-center gap-2 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-2.5 text-sm text-emerald-400">
                    <Check class="size-4 shrink-0" />
                    <span class="truncate"><strong>{{ lastRecorded.player }}</strong> &mdash; {{ lastRecorded.event }} ({{ formatEventTime(lastRecorded.minute, lastRecorded.second) }})</span>
                </div>
            </Transition>

            <!-- ===== EVENTS TIMELINE (hidden in fullscreen) ===== -->
            <div v-if="!isFullscreen" class="mt-5">
                <h3 class="mb-3 text-xs font-extrabold tracking-widest text-muted-foreground uppercase">
                    Timeline
                    <span class="ml-1 font-normal opacity-60">({{ match.events?.length ?? 0 }})</span>
                </h3>
                <EventTimeline
                    :events="sortedEvents"
                    :match="match"
                    :club-ulid="club.ulid"
                    :match-base="`${base}/${match.ulid}`"
                    show-delete
                    @delete="confirmRemoveEvent"
                />
            </div>
        </div>

        <!-- Delete event confirmation -->
        <Dialog v-model:open="showDeleteEventDialog">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Eliminar evento</DialogTitle>
                    <DialogDescription>
                        {{ deletingEventLabel ? `Se eliminará "${deletingEventLabel}".` : 'Se eliminará este evento.' }}
                        Esta acción no se puede deshacer.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex flex-col gap-2 pt-2">
                    <Button variant="destructive" class="w-full gap-2" @click="executeDeleteEvent">
                        <Trash2 class="size-4" />
                        Eliminar evento
                    </Button>
                    <Button variant="ghost" class="w-full" @click="showDeleteEventDialog = false">Cancelar</Button>
                </div>
            </DialogContent>
        </Dialog>
    </component>

</template>
