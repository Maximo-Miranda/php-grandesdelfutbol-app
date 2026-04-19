<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Calendar, ChevronRight, Pencil } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import UserAvatar from '@/components/UserAvatar.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { STAT_STYLES } from '@/pages/clubs/standings/partials/statStyles';
import type { BreadcrumbItem, Club } from '@/types';

type Player = { id: number; ulid: string; name: string; jersey_number: number | null; position: string | null; photo_url: string | null };

type Team = {
    id: number;
    ulid: string;
    name: string;
    color: string;
    bio: string | null;
    logo_url: string | null;
    cover_url: string | null;
    season: { ulid: string; name: string; is_active: boolean };
    coach: Player | null;
    captain: Player | null;
    players: Player[];
    players_count: number;
};

type RecentMatch = {
    ulid: string;
    title: string;
    scheduled_at: string;
    team_a_id: number | null;
    team_b_id: number | null;
    team_a_name: string | null;
    team_b_name: string | null;
    team_a_score: number | null;
    team_b_score: number | null;
    status: string;
    is_friendly: boolean;
};

const props = defineProps<{
    club: Club;
    team: Team;
    stats: { PJ: number; G: number; E: number; P: number; GF: number; GC: number; DG: number; Pts: number };
    recentMatches: RecentMatch[];
    canEdit: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubes', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
    { title: 'Posiciones', href: `/clubs/${props.club.ulid}/standings` },
    { title: 'Equipos', href: `/clubs/${props.club.ulid}/teams` },
    { title: props.team.name, href: `/clubs/${props.club.ulid}/teams/${props.team.ulid}` },
];

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('es', { day: 'numeric', month: 'short', year: 'numeric' });
}

type MatchResult = 'W' | 'D' | 'L' | 'F' | 'U';

type PresentedMatch = {
    ulid: string;
    date: string;
    opponentName: string;
    homeScore: number | null;
    awayScore: number | null;
    myScore: number | null;
    theirScore: number | null;
    isHome: boolean;
    result: MatchResult;
    isFriendly: boolean;
    status: string;
};

const presentedMatches = computed<PresentedMatch[]>(() => props.recentMatches.map((m): PresentedMatch => {
    const isHome = m.team_a_id === props.team.id;
    const myScore = isHome ? m.team_a_score : m.team_b_score;
    const theirScore = isHome ? m.team_b_score : m.team_a_score;
    const opponentName = (isHome ? m.team_b_name : m.team_a_name) ?? 'Rival externo';

    let result: MatchResult = 'U';
    if (m.is_friendly) {
        result = 'F';
    } else if (myScore !== null && theirScore !== null) {
        if (myScore > theirScore) result = 'W';
        else if (myScore < theirScore) result = 'L';
        else result = 'D';
    }

    return {
        ulid: m.ulid,
        date: m.scheduled_at,
        opponentName,
        homeScore: m.team_a_score,
        awayScore: m.team_b_score,
        myScore,
        theirScore,
        isHome,
        result,
        isFriendly: m.is_friendly,
        status: m.status,
    };
}));

const resultStyles: Record<MatchResult, { cls: string; label: string; letter: string }> = {
    W: { cls: 'bg-emerald-500/15 text-emerald-500 ring-1 ring-emerald-500/40', label: 'Victoria', letter: 'V' },
    D: { cls: 'bg-amber-500/15 text-amber-500 ring-1 ring-amber-500/40', label: 'Empate', letter: 'E' },
    L: { cls: 'bg-rose-500/15 text-rose-500 ring-1 ring-rose-500/40', label: 'Derrota', letter: 'D' },
    F: { cls: 'bg-muted text-muted-foreground ring-1 ring-border', label: 'Amistoso', letter: 'A' },
    U: { cls: 'bg-muted/40 text-muted-foreground ring-1 ring-border', label: 'Pendiente', letter: '-' },
};
</script>

<template>
    <Head :title="team.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl">
            <!-- Cover hero: image or team-color radial + diagonal pattern -->
            <div
                class="relative h-44 w-full overflow-hidden sm:h-52"
                :style="team.cover_url
                    ? { backgroundImage: `url(${team.cover_url})`, backgroundSize: 'cover', backgroundPosition: 'center' }
                    : { background: `radial-gradient(120% 80% at 20% 0%, ${team.color} 0%, ${team.color}cc 35%, #0b1220 85%)` }"
            >
                <!-- Hex/diagonal texture overlay -->
                <div
                    v-if="!team.cover_url"
                    class="pointer-events-none absolute inset-0 opacity-[0.12] mix-blend-overlay"
                    style="background-image: repeating-linear-gradient(135deg, transparent 0, transparent 18px, rgba(255,255,255,0.5) 18px, rgba(255,255,255,0.5) 19px), repeating-linear-gradient(45deg, transparent 0, transparent 18px, rgba(255,255,255,0.3) 18px, rgba(255,255,255,0.3) 19px);"
                />

                <!-- Corner spotlight -->
                <div class="pointer-events-none absolute -right-20 -top-20 size-60 rounded-full" :style="{ background: `radial-gradient(circle, ${team.color}66 0%, transparent 70%)` }" />

                <!-- Dark bottom gradient for readability -->
                <div class="pointer-events-none absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-background via-background/60 to-transparent" />

                <!-- Floating edit button -->
                <Link v-if="canEdit" :href="`/clubs/${club.ulid}/teams/${team.ulid}/edit`" class="absolute right-3 top-3 z-10">
                    <Button size="sm" variant="secondary" class="gap-1.5 shadow-lg backdrop-blur-md">
                        <Pencil class="size-3.5" />
                        <span class="hidden sm:inline">Editar</span>
                    </Button>
                </Link>

                <!-- Champions-style double accent stripes at bottom -->
                <div class="absolute inset-x-0 bottom-0 h-0.5 bg-border" />
                <div class="absolute inset-x-0 bottom-0 h-1" :style="{ background: `linear-gradient(90deg, transparent 0%, ${team.color} 50%, transparent 100%)` }" />
            </div>

            <!-- Identity block: logo + name, overlapping cover -->
            <div class="px-4">
                <div class="relative -mt-14 flex flex-col items-center gap-3 text-center sm:-mt-16 sm:flex-row sm:items-end sm:gap-5 sm:text-left">
                    <!-- Logo with glow ring -->
                    <div
                        class="relative z-10 shrink-0 rounded-full bg-background p-1.5 shadow-2xl ring-1 ring-border"
                        :style="{ boxShadow: `0 16px 40px -12px ${team.color}80` }"
                    >
                        <img
                            v-if="team.logo_url"
                            :src="team.logo_url"
                            class="block size-24 rounded-full object-cover sm:size-28"
                            alt=""
                        >
                        <span
                            v-else
                            class="flex size-24 items-center justify-center rounded-full text-3xl font-black tracking-tight text-white sm:size-28 sm:text-4xl"
                            :style="{ background: `linear-gradient(135deg, ${team.color}, ${team.color}bb)` }"
                        >{{ team.name.charAt(0).toUpperCase() }}</span>
                    </div>

                    <div class="min-w-0 flex-1 sm:pb-2">
                        <h1 class="truncate text-2xl font-black uppercase tracking-tight sm:text-4xl">{{ team.name }}</h1>
                        <div class="mt-2 flex flex-wrap items-center justify-center gap-2 text-xs sm:justify-start">
                            <span class="inline-flex items-center gap-1.5 rounded-md border border-border bg-muted/60 px-2 py-1 font-medium text-muted-foreground">
                                <Calendar class="size-3.5" />
                                {{ team.season.name }}
                            </span>
                            <span
                                v-if="team.season.is_active"
                                class="inline-flex items-center gap-1.5 rounded-md border border-emerald-500/40 bg-emerald-500/10 px-2 py-1 text-[10px] font-semibold uppercase tracking-wider text-emerald-500"
                            >
                                <span class="relative flex size-1.5">
                                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-500 opacity-75" />
                                    <span class="relative inline-flex size-1.5 rounded-full bg-emerald-500" />
                                </span>
                                En curso
                            </span>
                        </div>
                    </div>
                </div>

                <p v-if="team.bio" class="mt-4 text-center text-sm leading-relaxed text-muted-foreground sm:text-left">{{ team.bio }}</p>
            </div>

            <div class="px-4 pb-8">

                <div v-if="team.coach || team.captain" class="mt-6">
                    <h2 class="mb-2 text-sm font-semibold uppercase tracking-wider text-muted-foreground">Cuerpo técnico</h2>
                    <div class="flex flex-wrap gap-3">
                        <div v-if="team.coach" class="flex items-center gap-3 rounded-lg border border-border px-3 py-2">
                            <UserAvatar :src="team.coach.photo_url" :name="team.coach.name" class="size-10" />
                            <div>
                                <p class="text-sm font-medium">{{ team.coach.name }}</p>
                                <p class="text-xs text-muted-foreground">Director Técnico</p>
                            </div>
                        </div>
                        <div v-if="team.captain" class="flex items-center gap-3 rounded-lg border border-border px-3 py-2">
                            <UserAvatar :src="team.captain.photo_url" :name="team.captain.name" class="size-10" />
                            <div>
                                <p class="text-sm font-medium">{{ team.captain.name }}</p>
                                <p class="text-xs text-muted-foreground">Capitán</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-4 gap-2 rounded-lg border border-border bg-card p-3 sm:grid-cols-8">
                    <div class="text-center">
                        <p class="text-lg font-bold tabular-nums">{{ stats.PJ }}</p>
                        <p class="mt-0.5 text-xs font-bold uppercase tracking-wider" :class="STAT_STYLES.PJ.label">PJ</p>
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-bold tabular-nums">{{ stats.G }}</p>
                        <p class="mt-0.5 text-xs font-bold uppercase tracking-wider" :class="STAT_STYLES.G.label">G</p>
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-bold tabular-nums">{{ stats.E }}</p>
                        <p class="mt-0.5 text-xs font-bold uppercase tracking-wider" :class="STAT_STYLES.E.label">E</p>
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-bold tabular-nums">{{ stats.P }}</p>
                        <p class="mt-0.5 text-xs font-bold uppercase tracking-wider" :class="STAT_STYLES.P.label">P</p>
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-bold tabular-nums">{{ stats.GF }}</p>
                        <p class="mt-0.5 text-xs font-bold uppercase tracking-wider" :class="STAT_STYLES.GF.label">GF</p>
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-bold tabular-nums">{{ stats.GC }}</p>
                        <p class="mt-0.5 text-xs font-bold uppercase tracking-wider" :class="STAT_STYLES.GC.label">GC</p>
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-bold tabular-nums">{{ stats.DG > 0 ? '+' : '' }}{{ stats.DG }}</p>
                        <p class="mt-0.5 text-xs font-bold uppercase tracking-wider" :class="STAT_STYLES.DG.label">DG</p>
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-black tabular-nums" :class="STAT_STYLES.Pts.label">{{ stats.Pts }}</p>
                        <p class="mt-0.5 text-xs font-bold uppercase tracking-wider" :class="STAT_STYLES.Pts.label">Pts</p>
                    </div>
                </div>

                <div class="mt-8">
                    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-muted-foreground">Plantilla ({{ team.players_count }})</h2>
                    <div v-if="team.players.length === 0" class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        Sin jugadores registrados. Al confirmar asistencia a un partido con este equipo, los jugadores se agregarán automáticamente.
                    </div>
                    <div v-else class="grid gap-2 sm:grid-cols-2">
                        <Link
                            v-for="p in team.players"
                            :key="p.id"
                            :href="`/clubs/${club.ulid}/players/${p.ulid}`"
                            class="flex items-center gap-3 rounded-lg border border-border p-2 hover:bg-accent"
                        >
                            <UserAvatar :src="p.photo_url" :name="p.name" class="size-9" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ p.name }}</p>
                                <div class="flex items-center gap-2">
                                    <Badge v-if="p.position" variant="outline" class="text-[10px]">{{ p.position }}</Badge>
                                    <span v-if="p.jersey_number" class="text-xs text-muted-foreground">#{{ p.jersey_number }}</span>
                                </div>
                            </div>
                        </Link>
                    </div>
                </div>

                <div class="mt-8">
                    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-muted-foreground">Últimos partidos</h2>
                    <div v-if="presentedMatches.length === 0" class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        Sin partidos jugados aún.
                    </div>
                    <ul v-else class="space-y-2">
                        <li v-for="m in presentedMatches" :key="m.ulid">
                            <Link
                                :href="`/clubs/${club.ulid}/matches/${m.ulid}`"
                                class="group flex items-center gap-3 rounded-lg border border-border bg-card p-3 transition hover:border-muted-foreground/40 hover:bg-accent/60 active:scale-[0.99]"
                            >
                                <!-- Result pill -->
                                <span
                                    class="flex size-9 shrink-0 items-center justify-center rounded-lg text-sm font-black"
                                    :class="resultStyles[m.result].cls"
                                    :title="resultStyles[m.result].label"
                                    :aria-label="resultStyles[m.result].label"
                                >
                                    {{ resultStyles[m.result].letter }}
                                </span>

                                <!-- Match meta -->
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 text-sm font-medium">
                                        <span class="shrink-0 text-muted-foreground">vs</span>
                                        <span class="truncate">{{ m.opponentName }}</span>
                                    </div>
                                    <div class="mt-0.5 flex items-center gap-2 text-[11px] text-muted-foreground">
                                        <span>{{ formatDate(m.date) }}</span>
                                        <span v-if="m.isFriendly" class="rounded border border-amber-400/40 bg-amber-400/10 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wider text-amber-600">Amistoso</span>
                                        <span v-else class="text-[10px] uppercase tracking-wider">{{ m.isHome ? 'Local' : 'Visitante' }}</span>
                                    </div>
                                </div>

                                <!-- Score badge -->
                                <div
                                    v-if="m.myScore !== null && m.theirScore !== null"
                                    class="flex shrink-0 items-baseline gap-1 rounded-md border border-border bg-background px-2.5 py-1 tabular-nums"
                                >
                                    <span
                                        class="text-base font-bold"
                                        :class="{ 'text-emerald-500/80': m.result === 'W', 'text-rose-500/80': m.result === 'L' }"
                                    >{{ m.myScore }}</span>
                                    <span class="text-xs text-muted-foreground">-</span>
                                    <span class="text-base font-bold text-muted-foreground">{{ m.theirScore }}</span>
                                </div>
                                <span v-else class="shrink-0 text-[10px] uppercase tracking-wider text-muted-foreground">{{ m.status }}</span>

                                <ChevronRight class="size-4 shrink-0 text-muted-foreground/40 transition group-hover:translate-x-0.5 group-hover:text-muted-foreground" />
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
