<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, CircleDot, RectangleVertical } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, FootballMatch, MatchEvent } from '@/types';

type Props = { club: Club; match: FootballMatch };
const props = defineProps<Props>();

const base = `/clubs/${props.club.id}/matches`;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.id}` },
    { title: 'Partidos', href: base },
    { title: props.match.title, href: `${base}/${props.match.id}` },
    { title: 'Resumen', href: `${base}/${props.match.id}/summary` },
];

const teamAGoals = (props.match.events ?? []).filter(
    (e: MatchEvent) => e.event_type === 'goal' && props.match.attendances?.find(a => a.player_id === e.player_id)?.team === 'a',
).length;

const teamBGoals = (props.match.events ?? []).filter(
    (e: MatchEvent) => e.event_type === 'goal' && props.match.attendances?.find(a => a.player_id === e.player_id)?.team === 'b',
).length;

const sortedEvents = [...(props.match.events ?? [])].sort((a, b) => a.minute - b.minute);

const statusLabel: Record<string, string> = {
    upcoming: 'PROXIMO',
    in_progress: 'EN JUEGO',
    completed: 'FINALIZADO',
    cancelled: 'CANCELADO',
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
};

type PlayerStat = { name: string; team: string; goals: number; assists: number; yellowCards: number; redCards: number };
const playerStats = new Map<number, PlayerStat>();
for (const event of props.match.events ?? []) {
    if (!playerStats.has(event.player_id)) {
        const att = props.match.attendances?.find(a => a.player_id === event.player_id);
        playerStats.set(event.player_id, {
            name: event.player?.name ?? 'Unknown',
            team: att?.team === 'a' ? 'Eq. A' : att?.team === 'b' ? 'Eq. B' : '',
            goals: 0, assists: 0, yellowCards: 0, redCards: 0,
        });
    }
    const stat = playerStats.get(event.player_id)!;
    if (event.event_type === 'goal') stat.goals++;
    if (event.event_type === 'assist') stat.assists++;
    if (event.event_type === 'yellow_card') stat.yellowCards++;
    if (event.event_type === 'red_card') stat.redCards++;
}

function formatStatsDate(dateStr: string): string {
    const d = new Date(dateStr);
    return d.toLocaleDateString('es', { day: 'numeric', month: 'short', year: 'numeric' });
}

function finalizeStats() {
    router.post(`${base}/${props.match.id}/finalize-stats`);
}
</script>

<template>
    <Head title="Resumen del Partido" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <Link :href="base" class="mb-4 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                <ArrowLeft class="size-4" />Volver
            </Link>

            <!-- Score header -->
            <div class="mb-6 text-center">
                <Badge variant="outline" class="mb-2">{{ statusLabel[match.status] ?? match.status }}</Badge>
                <h1 class="text-lg font-medium">{{ match.title }}</h1>

                <div class="mt-4 flex items-center justify-center gap-6">
                    <div class="text-center">
                        <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Equipo A</p>
                        <p class="text-5xl font-bold">{{ teamAGoals }}</p>
                    </div>
                    <span class="text-2xl text-muted-foreground">-</span>
                    <div class="text-center">
                        <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Equipo B</p>
                        <p class="text-5xl font-bold">{{ teamBGoals }}</p>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-center gap-4">
                    <Link :href="`${base}/${match.id}/live`" class="text-sm text-muted-foreground hover:text-foreground">
                        Panel de control
                    </Link>
                    <span v-if="match.stats_finalized_at" class="text-sm text-primary">
                        Estadisticas acumuladas el {{ formatStatsDate(match.stats_finalized_at) }}
                    </span>
                    <Button v-else-if="match.status === 'completed'" size="sm" @click="finalizeStats">
                        Finalizar estadisticas
                    </Button>
                </div>
            </div>

            <!-- Timeline -->
            <div v-if="sortedEvents.length" class="mb-8">
                <h3 class="mb-4 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Timeline</h3>
                <div class="space-y-3">
                    <div v-for="event in sortedEvents" :key="event.id" class="flex items-start gap-3 rounded-lg border border-border p-3">
                        <span class="mt-0.5 text-sm font-medium text-muted-foreground">{{ event.minute }}'</span>
                        <CircleDot v-if="event.event_type === 'goal'" class="mt-0.5 size-4 text-primary" />
                        <RectangleVertical v-else-if="event.event_type === 'yellow_card'" class="mt-0.5 size-4 text-yellow-400" />
                        <RectangleVertical v-else-if="event.event_type === 'red_card'" class="mt-0.5 size-4 text-destructive" />
                        <CircleDot v-else class="mt-0.5 size-4 text-muted-foreground" />
                        <div>
                            <p class="font-medium">{{ event.player?.name }}</p>
                            <p class="text-sm text-muted-foreground">{{ eventLabel[event.event_type] ?? event.event_type }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Player stats -->
            <div v-if="playerStats.size">
                <h3 class="mb-4 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Estadisticas por jugador</h3>
                <div class="space-y-2">
                    <div v-for="[playerId, stat] in playerStats" :key="playerId" class="rounded-lg border border-border p-3">
                        <div class="flex items-center gap-3">
                            <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-bold">
                                {{ stat.name.charAt(0) }}
                            </div>
                            <span class="font-medium">{{ stat.name }}</span>
                            <Badge v-if="stat.team" variant="outline" class="text-xs">{{ stat.team }}</Badge>
                        </div>
                        <div class="mt-2 flex gap-4 text-sm text-muted-foreground">
                            <span v-if="stat.goals" class="flex items-center gap-1"><CircleDot class="size-3 text-primary" /> {{ stat.goals }} {{ stat.goals === 1 ? 'gol' : 'goles' }}</span>
                            <span v-if="stat.assists">{{ stat.assists }} {{ stat.assists === 1 ? 'asistencia' : 'asistencias' }}</span>
                            <span v-if="stat.yellowCards" class="flex items-center gap-1"><RectangleVertical class="size-3 text-yellow-400" /> {{ stat.yellowCards }} amarilla</span>
                            <span v-if="stat.redCards" class="flex items-center gap-1"><RectangleVertical class="size-3 text-destructive" /> {{ stat.redCards }} roja</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
