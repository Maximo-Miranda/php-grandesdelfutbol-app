<script setup lang="ts">
import { Link, usePoll } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, Clock, MapPin, Trophy, Users } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import EventIcon from '@/components/match/EventIcon.vue';
import MatchTeamsScore from '@/components/match/MatchTeamsScore.vue';
import VideoPlayer from '@/components/match/VideoPlayer.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import { EVENT_LABELS, EVENT_ICON_COLORS, countTeamGoals as countTeamGoalsUtil, getEventTeam as getEventTeamUtil } from '@/lib/match-events';
import { buildCanonicalUrl, formatDate, formatTime, truncateForMeta } from '@/lib/utils';
import type { FootballMatch, MatchEvent, MatchStatus, TeamSide } from '@/types';

type PublicClub = {
    ulid: string;
    slug: string;
    name: string;
    logo_url: string | null;
    is_public: boolean;
};

type Props = {
    match: FootballMatch & {
        team_a_logo_url?: string | null;
        team_b_logo_url?: string | null;
        season?: { name: string } | null;
    };
    club: PublicClub;
    isMember: boolean;
    s3VideoUrl?: string | null;
    appUrl: string;
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

const teamA = computed<TeamSide>(() => ({
    name: props.match.team_a_name,
    color: props.match.team_a_color,
    logo_url: props.match.team_a_logo_url ?? null,
    score: props.match.status === 'in_progress' ? teamAGoals.value : props.match.team_a_score,
}));

const teamB = computed<TeamSide | null>(() => {
    if (!props.match.team_b_name) {
        return null;
    }

    return {
        name: props.match.team_b_name,
        color: props.match.team_b_color,
        logo_url: props.match.team_b_logo_url ?? null,
        score: props.match.status === 'in_progress' ? teamBGoals.value : props.match.team_b_score,
    };
});

const matchStatus = computed<MatchStatus>(() => props.match.status);

const backHref = computed(() => props.club.is_public ? `/club/${props.club.slug}` : '/');
const backLabel = computed(() => props.club.is_public ? `Volver a ${props.club.name}` : 'Volver al inicio');

const canonicalUrl = computed(() => buildCanonicalUrl(props.appUrl, `/match/${props.match.share_token}`));

const seoTitle = computed(() => `${props.match.title} — ${props.club.name}`);

const seoDescription = computed(() => {
    const status = props.match.status === 'completed' ? 'Resultado' : props.match.status === 'in_progress' ? 'En vivo' : 'Próximo';
    const score = props.match.status === 'completed'
        ? ` ${props.match.team_a_score ?? 0}-${props.match.team_b_score ?? 0}.`
        : '.';

    return truncateForMeta(`${status}: ${props.match.team_a_name} vs ${props.match.team_b_name ?? 'Rival'}${score} ${formattedDate.value} en ${props.club.name}.`);
});

const ogImage = computed(() => props.club.logo_url ?? buildCanonicalUrl(props.appUrl, '/pwa-512x512.png'));

const attendanceCount = computed(() => (props.match.attendances ?? []).filter(a => a.status === 'confirmed').length);
</script>

<template>
    <SeoHead
        :title="seoTitle"
        :description="seoDescription"
        :canonical-url="canonicalUrl"
        :og-image="ogImage"
    />

    <div class="min-h-screen bg-background text-foreground">
        <PublicHeader />

        <section class="relative overflow-hidden bg-gradient-to-br from-emerald-950 via-slate-900 to-slate-950 pt-20 pb-10 sm:pt-24 sm:pb-14">
            <div
                class="pointer-events-none absolute inset-0 opacity-[0.07]"
                style="background-image: repeating-linear-gradient(0deg, transparent 0, transparent 40px, white 40px, white 41px), repeating-linear-gradient(90deg, transparent 0, transparent 40px, white 40px, white 41px);"
            />
            <div class="pointer-events-none absolute -top-24 right-0 size-96 rounded-full bg-emerald-500/20 blur-3xl" />
            <div class="pointer-events-none absolute -bottom-24 left-0 size-96 rounded-full bg-emerald-600/10 blur-3xl" />

            <div class="relative mx-auto max-w-3xl px-4 sm:px-6">
                <Link
                    :href="backHref"
                    class="mb-6 inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-white/60 transition-colors hover:bg-white/10 hover:text-white"
                >
                    <ArrowLeft class="size-4" />
                    <span class="max-w-[14rem] truncate">{{ backLabel }}</span>
                </Link>

                <div class="text-center sm:text-left">
                    <p class="mb-2 text-xs font-bold uppercase tracking-[0.25em] text-emerald-400/90">
                        <Link v-if="club.is_public" :href="`/club/${club.slug}`" class="hover:text-emerald-300">{{ club.name }}</Link>
                        <span v-else>{{ club.name }}</span>
                    </p>
                    <h1 class="mb-3 text-3xl font-extrabold tracking-tight text-white sm:text-4xl">{{ match.title }}</h1>
                    <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1.5 text-sm text-white/70 sm:justify-start">
                        <span class="inline-flex items-center gap-1.5">
                            <CalendarDays class="size-4" />
                            {{ formattedDate }}
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <Clock class="size-4" />
                            {{ formatTime(match.scheduled_at) }}
                        </span>
                        <span v-if="match.field" class="inline-flex items-center gap-1.5">
                            <MapPin class="size-4" />
                            {{ match.field.name }}
                        </span>
                        <span v-if="match.season" class="inline-flex items-center gap-1.5">
                            <Trophy class="size-4 text-violet-300" />
                            {{ match.season.name }}
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-b border-border py-10 sm:py-12">
            <div class="mx-auto max-w-3xl px-4 sm:px-6">
                <div class="rounded-2xl border border-border bg-card p-6 sm:p-8">
                    <div v-if="isLive" class="mb-4 flex items-center justify-center gap-2">
                        <span class="relative flex size-2">
                            <span class="absolute inline-flex size-full animate-ping rounded-full bg-orange-500 opacity-75" />
                            <span class="relative inline-flex size-2 rounded-full bg-orange-500" />
                        </span>
                        <span class="font-mono text-sm font-bold tabular-nums text-orange-500">{{ clockDisplay }}</span>
                    </div>

                    <MatchTeamsScore
                        :team-a="teamA"
                        :team-b="teamB"
                        :status="matchStatus"
                        :is-friendly="match.is_friendly ?? false"
                        :scheduled-at="match.scheduled_at"
                    />

                    <div class="mt-6 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 border-t border-border/60 pt-4 text-xs text-muted-foreground">
                        <span class="inline-flex items-center gap-1">
                            <Users class="size-3.5" />
                            {{ attendanceCount }}/{{ match.max_players }} confirmados
                        </span>
                        <span
                            v-if="match.is_friendly"
                            class="rounded-full border border-amber-500/40 bg-amber-500/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-500"
                        >Amistoso</span>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="match.video_upload?.youtube_embed_url || s3VideoUrl" class="border-b border-border py-8 sm:py-10">
            <div class="mx-auto max-w-3xl px-4 sm:px-6">
                <p class="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary">Video</p>
                <div v-if="match.video_upload?.youtube_embed_url" class="aspect-video w-full overflow-hidden rounded-xl border border-border">
                    <iframe
                        :src="match.video_upload.youtube_embed_url"
                        class="h-full w-full"
                        allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                    />
                </div>
                <VideoPlayer v-else-if="s3VideoUrl" :src="s3VideoUrl" />
            </div>
        </section>

        <section class="py-10 sm:py-14">
            <div class="mx-auto max-w-3xl px-4 sm:px-6">
                <h2 class="mb-6 flex items-center gap-2 text-xl font-bold sm:text-2xl">
                    <Clock class="size-5 text-primary" />
                    Timeline
                    <span v-if="sortedEvents.length" class="font-mono text-sm font-normal text-muted-foreground">· {{ sortedEvents.length }}</span>
                </h2>

                <div v-if="sortedEvents.length === 0" class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                    {{ isLive ? 'El partido está en curso. Los eventos aparecerán aquí.' : 'No se registraron eventos.' }}
                </div>

                <div v-else class="relative space-y-0">
                    <div class="absolute inset-y-0 left-1/2 w-px -translate-x-1/2 bg-border" />

                    <div
                        v-for="event in sortedEvents"
                        :key="event.id"
                        class="relative flex items-center gap-2 py-1.5"
                        :class="getEventTeam(event) === 'b' ? 'flex-row-reverse' : ''"
                    >
                        <div
                            class="flex min-w-0 flex-1 items-center gap-2.5 rounded-lg border border-border bg-card px-3 py-2.5"
                            :class="getEventTeam(event) === 'b' ? 'flex-row-reverse text-right' : ''"
                            :style="{
                                borderLeftWidth: getEventTeam(event) === 'a' ? '3px' : undefined,
                                borderRightWidth: getEventTeam(event) === 'b' ? '3px' : undefined,
                                borderLeftColor: getEventTeam(event) === 'a' ? (match.team_a_color ?? '#6b7280') : undefined,
                                borderRightColor: getEventTeam(event) === 'b' ? (match.team_b_color ?? '#6b7280') : undefined,
                            }"
                        >
                            <div class="shrink-0">
                                <EventIcon :event-type="event.event_type" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold" :class="EVENT_ICON_COLORS[event.event_type] ?? 'text-foreground'">
                                    {{ EVENT_LABELS[event.event_type] ?? event.event_type }}
                                </p>
                                <div class="flex items-center gap-1.5 text-xs" :class="getEventTeam(event) === 'b' ? 'flex-row-reverse' : ''">
                                    <span
                                        v-if="getEventTeam(event)"
                                        class="size-2 shrink-0 rounded-full"
                                        :style="{ backgroundColor: getEventTeam(event) === 'a' ? (match.team_a_color ?? '#6b7280') : (match.team_b_color ?? '#6b7280') }"
                                    />
                                    <span class="truncate text-foreground/70">
                                        <template v-if="isMember && event.player">
                                            {{ event.player.display_name }}
                                            <template v-if="event.event_type === 'substitution' && event.related_player">
                                                <span class="text-blue-400"> → </span>{{ event.related_player.display_name }}
                                            </template>
                                        </template>
                                        <template v-else-if="getEventTeam(event)">
                                            {{ getEventTeam(event) === 'a' ? match.team_a_name : match.team_b_name }}
                                        </template>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="z-10 flex shrink-0 flex-col items-center">
                            <span class="flex size-10 items-center justify-center rounded-full border border-border bg-card text-xs font-bold tabular-nums">
                                {{ event.minute }}<span class="text-[8px] text-muted-foreground">'</span>
                            </span>
                            <span v-if="event.second > 0" class="mt-0.5 text-[8px] tabular-nums text-muted-foreground">
                                {{ String(event.second).padStart(2, '0') }}s
                            </span>
                        </div>

                        <div class="flex-1" />
                    </div>
                </div>
            </div>
        </section>

        <footer class="border-t border-border bg-background py-8">
            <div class="mx-auto flex max-w-5xl flex-col items-center gap-3 px-4 text-center text-sm text-muted-foreground sm:px-6">
                <AppLogo />
                <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1">
                    <Link v-if="club.is_public" :href="`/club/${club.slug}`" class="hover:text-foreground">{{ club.name }}</Link>
                    <span v-if="club.is_public" class="text-muted-foreground/40">·</span>
                    <Link href="/news" class="hover:text-foreground">Noticias</Link>
                    <span class="text-muted-foreground/40">·</span>
                    <Link href="/terms" class="hover:text-foreground">Términos</Link>
                </div>
                <p class="text-xs">&copy; {{ new Date().getFullYear() }} Grandes del Fútbol</p>
            </div>
        </footer>
    </div>
</template>
