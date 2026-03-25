<script setup lang="ts">
import { Head, Link, usePage, usePoll } from '@inertiajs/vue3';
import { Calendar, Clock, MapPin } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import EventIcon from '@/components/match/EventIcon.vue';
import LiveScoreboard from '@/components/match/LiveScoreboard.vue';
import VideoPlayer from '@/components/match/VideoPlayer.vue';
import { EVENT_LABELS, EVENT_ICON_COLORS, countTeamGoals as countTeamGoalsUtil, getEventTeam as getEventTeamUtil } from '@/lib/match-events';
import { formatDate, formatTime } from '@/lib/utils';
import type { FootballMatch, MatchEvent } from '@/types';

type Props = {
    match: FootballMatch & { club: { name: string } };
    isMember: boolean;
    s3VideoUrl?: string | null;
};
const props = defineProps<Props>();

const isLive = computed(() => props.match.status === 'in_progress');

if (isLive.value) {
    usePoll(10000);
}

const teamAGoals = computed(() => countTeamGoalsUtil(props.match, 'a'));
const teamBGoals = computed(() => countTeamGoalsUtil(props.match, 'b'));

const sortedEvents = computed(() =>
    [...(props.match.events ?? [])].sort((a, b) => a.minute - b.minute || a.second - b.second),
);

function getEventTeam(event: MatchEvent): 'a' | 'b' | null {
    return getEventTeamUtil(props.match, event);
}

// Clock
const clockDisplay = ref('00:00');
let clockTimer: ReturnType<typeof setInterval> | null = null;

function updateClock() {
    if (props.match.started_at) {
        const elapsed = Math.max(0, Date.now() - new Date(props.match.started_at).getTime());
        const totalSeconds = Math.floor(elapsed / 1000);
        clockDisplay.value = `${String(Math.floor(totalSeconds / 60)).padStart(2, '0')}:${String(totalSeconds % 60).padStart(2, '0')}`;
    }
}

onMounted(() => {
    if (isLive.value && props.match.started_at) {
        updateClock();
        clockTimer = setInterval(updateClock, 1000);
    }
});

onUnmounted(() => { if (clockTimer) clearInterval(clockTimer); });

const formattedDate = computed(() =>
    formatDate(props.match.scheduled_at, { weekday: 'long', day: 'numeric', month: 'long' })
        .replace(/^\w/, c => c.toUpperCase()),
);

const page = usePage();
const isAuthenticated = computed(() => !!(page.props.auth as any)?.user);
const currentClub = computed(() => (page.props as any).currentClub);
const homeUrl = computed(() => {
    if (isAuthenticated.value && currentClub.value?.ulid) {
        return `/clubs/${currentClub.value.ulid}`;
    }
    return '/';
});
</script>

<template>
    <Head :title="match.title" />
    <div class="min-h-screen bg-background">
        <div class="mx-auto max-w-2xl px-4 py-6">
            <!-- Top bar -->
            <div class="mb-4 flex items-center justify-between">
                <Link
                    :href="homeUrl"
                    class="text-xs font-medium text-muted-foreground transition-colors hover:text-foreground"
                >
                    {{ isAuthenticated ? '&larr; Mi club' : '&larr; Inicio' }}
                </Link>
                <p class="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                    {{ match.club.name }}
                </p>
            </div>

            <!-- Scoreboard -->
            <LiveScoreboard
                :match="match"
                :clock-display="clockDisplay"
                :team-a-goals="teamAGoals"
                :team-b-goals="teamBGoals"
            />

            <!-- Match info -->
            <div class="mt-4 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                <span class="flex items-center gap-1">
                    <Calendar class="size-3.5" />
                    {{ formattedDate }}
                </span>
                <span class="flex items-center gap-1">
                    <Clock class="size-3.5" />
                    {{ formatTime(match.scheduled_at) }}
                </span>
                <span v-if="match.field" class="flex items-center gap-1">
                    <MapPin class="size-3.5" />
                    {{ match.field.name }}
                </span>
            </div>

            <!-- Video -->
            <div v-if="match.video_upload?.youtube_embed_url" class="mt-4">
                <div class="aspect-video w-full overflow-hidden rounded-xl border border-border">
                    <iframe
                        :src="match.video_upload.youtube_embed_url"
                        class="h-full w-full"
                        allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                    />
                </div>
            </div>
            <div v-else-if="s3VideoUrl" class="mt-4">
                <VideoPlayer :src="s3VideoUrl" />
            </div>

            <!-- Timeline -->
            <div v-if="sortedEvents.length" class="mt-6">
                <h3 class="mb-4 text-xs font-extrabold tracking-widest text-muted-foreground uppercase">
                    Timeline
                    <span class="ml-1 font-normal opacity-60">({{ sortedEvents.length }})</span>
                </h3>

                <div class="relative space-y-0">
                    <!-- Center line -->
                    <div class="absolute inset-y-0 left-1/2 w-px -translate-x-1/2 bg-border"></div>

                    <div
                        v-for="event in sortedEvents"
                        :key="event.id"
                        class="relative flex items-center gap-2 py-1.5"
                        :class="getEventTeam(event) === 'b' ? 'flex-row-reverse' : ''"
                    >
                        <!-- Event card -->
                        <div
                            class="flex min-w-0 flex-1 items-center gap-2.5 rounded-lg border border-border px-3 py-2.5"
                            :class="getEventTeam(event) === 'b' ? 'flex-row-reverse text-right' : ''"
                            :style="{
                                borderLeftWidth: getEventTeam(event) === 'a' ? '3px' : undefined,
                                borderRightWidth: getEventTeam(event) === 'b' ? '3px' : undefined,
                                borderLeftColor: getEventTeam(event) === 'a' ? (match.team_a_color ?? '#6b7280') : undefined,
                                borderRightColor: getEventTeam(event) === 'b' ? (match.team_b_color ?? '#6b7280') : undefined,
                            }"
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
                                <div class="flex items-center gap-1.5 text-xs" :class="getEventTeam(event) === 'b' ? 'flex-row-reverse' : ''">
                                    <span
                                        v-if="getEventTeam(event)"
                                        class="size-2 shrink-0 rounded-full"
                                        :style="{ backgroundColor: getEventTeam(event) === 'a' ? (match.team_a_color ?? '#6b7280') : (match.team_b_color ?? '#6b7280') }"
                                    ></span>
                                    <span class="truncate text-foreground/70">
                                        <template v-if="isMember && event.player">
                                            {{ event.player.display_name }}
                                            <template v-if="event.event_type === 'substitution' && event.related_player">
                                                <span class="text-blue-400"> &rarr; </span>{{ event.related_player.display_name }}
                                            </template>
                                        </template>
                                        <template v-else-if="getEventTeam(event)">
                                            {{ getEventTeam(event) === 'a' ? match.team_a_name : match.team_b_name }}
                                        </template>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Minute bubble (center) -->
                        <div class="z-10 flex shrink-0 flex-col items-center">
                            <span class="flex size-10 items-center justify-center rounded-full border border-border bg-card text-xs font-bold tabular-nums">
                                {{ event.minute }}<span class="text-[8px] text-muted-foreground">'</span>
                            </span>
                            <span v-if="event.second > 0" class="mt-0.5 text-[8px] tabular-nums text-muted-foreground">
                                {{ String(event.second).padStart(2, '0') }}s
                            </span>
                        </div>

                        <!-- Spacer -->
                        <div class="flex-1"></div>
                    </div>
                </div>
            </div>

            <div v-else class="mt-6 text-center text-sm text-muted-foreground">
                {{ isLive ? 'El partido esta en curso. Los eventos apareceran aqui.' : 'No se registraron eventos.' }}
            </div>

            <!-- Footer -->
            <div class="mt-10 text-center text-[10px] text-muted-foreground/50">
                Grandes del Futbol
            </div>
        </div>
    </div>
</template>
