<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Pencil, Star, Trash2, UserPlus } from 'lucide-vue-next';
import { ref } from 'vue';
import EventIcon from '@/components/match/EventIcon.vue';
import { EVENT_LABELS, EVENT_ICON_COLORS, getEventTeam as getEventTeamUtil } from '@/lib/match-events';
import type { FootballMatch, MatchEvent } from '@/types';

const props = defineProps<{
    events: MatchEvent[];
    match: FootballMatch;
    clubUlid: string;
    matchBase: string;
    showDelete?: boolean;
}>();

const emit = defineEmits<{
    delete: [eventUlid: string];
    edit: [event: MatchEvent];
}>();

const assignableEventTypes = new Set([
    'goal', 'assist', 'yellow_card', 'red_card',
    'penalty_scored', 'penalty_missed', 'own_goal',
    'save', 'foul', 'handball', 'substitution', 'injury', 'free_kick',
]);

function canAssignPlayer(event: MatchEvent): boolean {
    return !!event.team && assignableEventTypes.has(event.event_type);
}

function getEventTeam(event: MatchEvent): 'a' | 'b' | null {
    return getEventTeamUtil(props.match, event);
}

function teamName(team: 'a' | 'b' | null): string {
    if (team === 'a') return props.match.team_a_name;
    if (team === 'b') return props.match.team_b_name;
    return '';
}

// --- Inline player assignment ---
const expandedEventUlid = ref<string | null>(null);
const assigningPlayerId = ref<number | null>(null);
const assignSearchQuery = ref('');

function toggleAssignPanel(eventUlid: string) {
    if (expandedEventUlid.value === eventUlid) {
        expandedEventUlid.value = null;
    } else {
        expandedEventUlid.value = eventUlid;
        assignSearchQuery.value = '';
    }
}

function getFilteredTeamPlayers(team: 'a' | 'b') {
    const players = props.match.attendances?.filter(a => a.team === team) ?? [];
    const q = assignSearchQuery.value.toLowerCase().trim();
    if (!q) return players;
    return players.filter(att =>
        att.player?.display_name?.toLowerCase().includes(q)
        || String(att.player?.jersey_number ?? '').includes(q),
    );
}

function selectFirstFiltered(event: MatchEvent) {
    if (!event.team) return;
    const players = getFilteredTeamPlayers(event.team);
    const first = players.find(att => att.player_id !== event.player_id);
    if (first) {
        assignPlayer(event.ulid, first.player_id);
    }
}

const togglingHighlightUlid = ref<string | null>(null);

function toggleHighlight(event: MatchEvent) {
    togglingHighlightUlid.value = event.ulid;
    router.patch(`${props.matchBase}/events/${event.ulid}`, {
        highlighted: !event.highlighted,
    }, {
        preserveScroll: true,
        onFinish: () => { togglingHighlightUlid.value = null; },
    });
}

function assignPlayer(eventUlid: string, playerId: number) {
    assigningPlayerId.value = playerId;
    router.patch(`${props.matchBase}/events/${eventUlid}`, {
        player_id: playerId,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            expandedEventUlid.value = null;
            assigningPlayerId.value = null;
        },
        onError: () => {
            assigningPlayerId.value = null;
        },
    });
}
</script>

<template>
    <div v-if="events.length" class="relative space-y-0">
        <div class="absolute top-0 bottom-0 left-[18px] w-px bg-border"></div>

        <div
            v-for="event in events"
            :key="event.id"
            class="group relative flex items-start gap-3 py-1.5"
        >
            <!-- Minute bubble -->
            <div class="z-10 mt-1 flex shrink-0 flex-col items-center">
                <span class="flex size-10 items-center justify-center rounded-full border border-border bg-card text-xs font-bold tabular-nums">
                    {{ event.minute }}<span class="text-[8px] text-muted-foreground">'</span>
                </span>
                <span v-if="event.second > 0" class="mt-0.5 text-[8px] tabular-nums text-muted-foreground">
                    {{ String(event.second).padStart(2, '0') }}s
                </span>
            </div>

            <!-- Event card -->
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-3 rounded-lg border bg-card/50 px-3 py-2.5"
                    :class="expandedEventUlid === event.ulid
                        ? 'border-sky-500/40 rounded-b-none'
                        : 'border-border'"
                >
                    <!-- Icon -->
                    <div class="shrink-0">
                        <EventIcon :event-type="event.event_type" />
                    </div>

                    <!-- Content -->
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold" :class="EVENT_ICON_COLORS[event.event_type] ?? 'text-foreground'">
                            {{ EVENT_LABELS[event.event_type] ?? event.event_type }}
                        </p>
                        <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <Link
                                v-if="event.player"
                                :href="`/clubs/${clubUlid}/players/${event.player.ulid}`"
                                class="truncate hover:text-primary hover:underline"
                            >{{ event.player.display_name }}</Link>
                            <!-- Substitution: show → player in -->
                            <template v-if="event.event_type === 'substitution' && event.related_player">
                                <span class="text-blue-400">&rarr;</span>
                                <Link
                                    :href="`/clubs/${clubUlid}/players/${event.related_player.ulid}`"
                                    class="truncate hover:text-primary hover:underline"
                                >{{ event.related_player.display_name }}</Link>
                            </template>
                            <template v-if="(event.player || event.related_player) && getEventTeam(event)">
                                <span class="text-border">&middot;</span>
                            </template>
                            <span v-if="getEventTeam(event)" class="truncate">
                                {{ teamName(getEventTeam(event)) }}
                            </span>
                        </div>
                    </div>

                    <!-- Highlight toggle (star) -->
                    <button
                        v-if="showDelete && event.player_id"
                        :disabled="togglingHighlightUlid === event.ulid"
                        class="shrink-0 rounded-md p-1.5 transition-colors disabled:opacity-50"
                        :class="event.highlighted
                            ? 'text-amber-400 hover:bg-amber-500/10'
                            : 'text-muted-foreground/40 hover:bg-accent hover:text-amber-400'"
                        @click="toggleHighlight(event)"
                    >
                        <Star class="size-3.5" :class="event.highlighted ? 'fill-amber-400' : ''" />
                    </button>

                    <!-- Edit button -->
                    <button
                        v-if="showDelete"
                        class="shrink-0 rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        @click="emit('edit', event)"
                    >
                        <Pencil class="size-3.5" />
                    </button>

                    <!-- Assign/Change player button -->
                    <button
                        v-if="showDelete && canAssignPlayer(event)"
                        class="shrink-0 rounded-md px-2 py-1 text-[10px] font-medium transition-colors"
                        :class="expandedEventUlid === event.ulid
                            ? 'bg-sky-500/20 text-sky-400'
                            : event.player_id
                                ? 'border border-border text-muted-foreground hover:bg-accent'
                                : 'border border-amber-500/30 bg-amber-500/10 text-amber-400 hover:bg-amber-500/20'"
                        @click="toggleAssignPanel(event.ulid)"
                    >
                        <span class="flex items-center gap-1">
                            <UserPlus class="size-3.5" />
                            <template v-if="expandedEventUlid !== event.ulid">
                                {{ event.player_id ? 'Cambiar' : 'Asignar' }}
                            </template>
                            <span v-else>Cerrar</span>
                        </span>
                    </button>

                    <!-- Delete button -->
                    <button
                        v-if="showDelete"
                        class="shrink-0 rounded-md p-1.5 text-destructive/60 transition-colors hover:bg-destructive/10 hover:text-destructive"
                        @click="emit('delete', event.ulid)"
                    >
                        <Trash2 class="size-4" />
                    </button>
                </div>

                <!-- Inline player selector panel -->
                <div
                    v-if="expandedEventUlid === event.ulid && event.team"
                    class="rounded-b-lg border border-t-0 border-sky-500/40 bg-sky-500/5 px-3 py-2.5"
                >
                    <p class="mb-2 text-[10px] font-bold tracking-wider text-sky-400 uppercase">
                        Toca el jugador para {{ event.player_id ? 'cambiar' : 'asignar' }}
                    </p>
                    <!-- Search -->
                    <input
                        v-model="assignSearchQuery"
                        type="search"
                        enterkeyhint="done"
                        placeholder="Buscar por nombre o numero..."
                        class="mb-2 w-full rounded-lg border border-border bg-card/80 px-3 py-2 text-xs outline-none placeholder:text-muted-foreground focus:ring-1 focus:ring-sky-400/40"
                        @keydown.enter.prevent="selectFirstFiltered(event)"
                    />
                    <div class="grid grid-cols-2 gap-1.5 max-h-48 overflow-y-auto">
                        <button
                            v-for="att in getFilteredTeamPlayers(event.team)"
                            :key="att.player_id"
                            :disabled="assigningPlayerId !== null || att.player_id === event.player_id"
                            class="flex min-h-[44px] items-center gap-2 rounded-lg border px-2.5 py-2 text-left transition-all active:scale-[0.97] disabled:opacity-50"
                            :class="att.player_id === event.player_id
                                ? 'border-sky-500/40 bg-sky-500/10'
                                : 'border-border bg-card/80 hover:bg-accent'"
                            @click="assignPlayer(event.ulid, att.player_id)"
                        >
                            <span
                                class="flex size-7 shrink-0 items-center justify-center rounded-full text-[11px] font-bold text-white"
                                :style="{ backgroundColor: event.team === 'a' ? (match.team_a_color ?? '#6b7280') : (match.team_b_color ?? '#6b7280') }"
                            >{{ att.player?.jersey_number ?? att.player?.display_name?.charAt(0) }}</span>
                            <span class="min-w-0 truncate text-xs font-medium">{{ att.player?.display_name }}</span>
                        </button>
                    </div>
                    <p v-if="getFilteredTeamPlayers(event.team).length === 0" class="py-2 text-center text-xs text-muted-foreground">
                        Sin resultados
                    </p>
                </div>
            </div>
        </div>
    </div>
    <p v-else class="text-center text-sm text-muted-foreground">No hay eventos registrados.</p>
</template>
