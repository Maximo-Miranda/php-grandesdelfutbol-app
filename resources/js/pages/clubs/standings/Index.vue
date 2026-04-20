<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { CalendarDays, CalendarRange, ChevronDown, Plus, RefreshCw, Settings, Trophy, UserPlus, Users } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/utils';
import type { BreadcrumbItem, Club, MatchStatus, Player, TeamSide } from '@/types';
import PlayerStandingsTable from './partials/PlayerStandingsTable.vue';
import SeasonMatchesView from './partials/SeasonMatchesView.vue';
import SeasonSelector from './partials/SeasonSelector.vue';
import TeamStandingsTable from './partials/TeamStandingsTable.vue';

type Season = {
    ulid: string;
    name: string;
    matches_count: number;
    status: string;
    completed_at: string | null;
    is_active: boolean;
};

type SelectedSeason = Season & {
    starts_on: string | null;
    ends_on: string | null;
};

type SeasonMatch = {
    ulid: string;
    scheduled_at: string;
    status: MatchStatus;
    is_friendly: boolean;
    team_a: TeamSide;
    team_b: TeamSide | null;
};

type StandingRow = {
    team_id: number;
    team_ulid: string;
    name: string;
    color: string;
    logo_url: string | null;
    PJ: number;
    G: number;
    E: number;
    P: number;
    GF: number;
    GC: number;
    DG: number;
    Pts: number;
    last5: Array<'W' | 'D' | 'L' | 'F'>;
};

const props = defineProps<{
    club: Club;
    isAdmin: boolean;
    tab: string;
    seasons: Season[];
    selectedSeason: SelectedSeason;
    progress: { played: number; completed: number; total: number };
    teamStandings: StandingRow[];
    seasonMatches?: SeasonMatch[];
    players: { data: Player[] };
    goalkeepers: Player[];
}>();

type TabKey = 'teams' | 'players' | 'matches';

const activeTab = computed<TabKey>(() => {
    if (props.tab === 'players') return 'players';
    if (props.tab === 'matches') return 'matches';
    return 'teams';
});

const progressPct = computed(() =>
    props.progress.total > 0 ? Math.min(100, Math.round((props.progress.completed / props.progress.total) * 100)) : 0,
);

const seasonRange = computed(() => {
    const start = props.selectedSeason.starts_on;
    const end = props.selectedSeason.ends_on;
    if (!start && !end) return null;
    const fmt = (iso: string) => formatDate(iso, { day: 'numeric', month: 'short' });
    return `${start ? fmt(start) : '?'} — ${end ? fmt(end) : '?'}`;
});

function changeTab(value: TabKey): void {
    router.get(window.location.pathname, { tab: value, season: props.selectedSeason.ulid }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

const STANDINGS_PROPS = ['teamStandings', 'seasonMatches', 'players', 'goalkeepers', 'progress', 'selectedSeason'];

// Auto-refresh every 30s in case match events change in another tab/page.
// Returns a controller so we can pause/resume on visibility changes.
const poll = usePoll(30000, { only: STANDINGS_PROPS }, { autoStart: true });

const refreshing = ref(false);

function manualRefresh(): void {
    refreshing.value = true;
    router.reload({
        only: STANDINGS_PROPS,
        onFinish: () => { refreshing.value = false; },
    });
}

function handleVisibility(): void {
    if (document.visibilityState === 'visible') {
        router.reload({ only: STANDINGS_PROPS });
        poll.start();
    } else {
        poll.stop();
    }
}

onMounted(() => {
    document.addEventListener('visibilitychange', handleVisibility);
});
onUnmounted(() => {
    document.removeEventListener('visibilitychange', handleVisibility);
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubes', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
    { title: 'Posiciones', href: `/clubs/${props.club.ulid}/standings` },
];
</script>

<template>
    <Head :title="`${club.name} - Posiciones`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <div class="mb-6 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-bold">Posiciones</h1>
                    <p class="text-sm text-muted-foreground">Tabla de equipos y jugadores por temporada</p>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        variant="ghost"
                        size="icon"
                        :disabled="refreshing"
                        :title="refreshing ? 'Actualizando...' : 'Actualizar datos'"
                        @click="manualRefresh"
                    >
                        <RefreshCw class="size-4" :class="{ 'animate-spin': refreshing }" />
                    </Button>
                    <SeasonSelector :seasons="seasons" :selected="selectedSeason.ulid" :tab="activeTab" />
                </div>
            </div>

            <div class="mb-6 rounded-lg border border-border bg-card p-4">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold">{{ selectedSeason.name }}</span>
                        <span
                            v-if="selectedSeason.is_active"
                            class="rounded-full border border-emerald-500/40 bg-emerald-500/15 px-2 py-0.5 text-[10px] font-semibold text-emerald-500"
                        >Activa</span>
                        <span v-else class="rounded-full border border-muted bg-muted/40 px-2 py-0.5 text-[10px] font-semibold text-muted-foreground">Completada</span>
                        <span v-if="seasonRange" class="hidden text-xs text-muted-foreground sm:inline">· {{ seasonRange }}</span>
                    </div>
                    <span class="text-muted-foreground">
                        Partidos: <span class="font-semibold text-foreground">{{ progress.completed }}/{{ progress.total }}</span>
                    </span>
                </div>
                <p v-if="seasonRange" class="mb-2 text-xs text-muted-foreground sm:hidden">{{ seasonRange }}</p>
                <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-full bg-primary transition-all"
                        :style="{ width: `${progressPct}%` }"
                    />
                </div>
            </div>

            <div class="mb-6 flex gap-2 border-b border-border">
                <Button
                    variant="ghost"
                    size="sm"
                    class="rounded-none border-b-2"
                    :class="activeTab === 'teams' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground'"
                    @click="changeTab('teams')"
                >
                    <Trophy class="mr-2 size-4" />
                    Equipos
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    class="rounded-none border-b-2"
                    :class="activeTab === 'players' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground'"
                    @click="changeTab('players')"
                >
                    <Users class="mr-2 size-4" />
                    Jugadores
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    class="rounded-none border-b-2"
                    :class="activeTab === 'matches' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground'"
                    @click="changeTab('matches')"
                >
                    <CalendarDays class="mr-2 size-4" />
                    Calendario
                </Button>
            </div>

            <template v-if="activeTab === 'teams'">
                <div v-if="isAdmin" class="mb-4 flex items-center justify-end gap-1">
                    <Link :href="`/clubs/${club.ulid}/teams/create`">
                        <Button size="sm" class="h-8"><Plus class="mr-1 size-3.5" />Crear equipo</Button>
                    </Link>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" size="sm" class="h-8">
                                Más
                                <ChevronDown class="ml-1 size-3.5" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-52">
                            <DropdownMenuLabel>Administración</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem as-child>
                                <Link :href="`/clubs/${club.ulid}/teams`" class="flex w-full items-center">
                                    <Settings class="mr-2 size-4" />Gestionar equipos
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <Link :href="`/clubs/${club.ulid}/seasons`" class="flex w-full items-center">
                                    <CalendarRange class="mr-2 size-4" />Temporadas
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
                <TeamStandingsTable :club="club" :standings="teamStandings" />
            </template>

            <template v-else-if="activeTab === 'players'">
                <div v-if="isAdmin" class="mb-4 flex items-center justify-end gap-1">
                    <Link :href="`/clubs/${club.ulid}/players/create`">
                        <Button size="sm" class="h-8"><Plus class="mr-1 size-3.5" />Crear jugador</Button>
                    </Link>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" size="sm" class="h-8">
                                Más
                                <ChevronDown class="ml-1 size-3.5" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-52">
                            <DropdownMenuLabel>Administración</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem as-child>
                                <Link :href="`/clubs/${club.ulid}/members`" class="flex w-full items-center">
                                    <UserPlus class="mr-2 size-4" />Invitar miembros
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
                <PlayerStandingsTable
                    :club="club"
                    :players="players"
                    :goalkeepers="goalkeepers"
                    :is-admin="isAdmin"
                />
            </template>

            <template v-else>
                <SeasonMatchesView :club="club" :matches="seasonMatches" />
            </template>
        </div>
    </AppLayout>
</template>
