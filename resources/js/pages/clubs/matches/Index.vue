<script setup lang="ts">
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import { CalendarDays, Clock, MapPin, Plus, Trophy, Users } from 'lucide-vue-next';
import MatchTeamsScore from '@/components/match/MatchTeamsScore.vue';
import { Button } from '@/components/ui/button';
import { useClubPermissions } from '@/composables/useClubPermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate as fmtDate, formatTime as fmtTime } from '@/lib/utils';
import type { BreadcrumbItem, Club, FootballMatch } from '@/types';

function teamASide(m: FootballMatch) {
    return {
        name: m.team_a?.name ?? m.team_a_name,
        color: m.team_a?.color ?? m.team_a_color,
        logo_url: m.team_a?.logo_url ?? null,
        score: m.team_a_score,
    };
}

function teamBSide(m: FootballMatch) {
    const hasB = m.team_b || m.team_b_name;
    if (!hasB) return null;
    return {
        name: m.team_b?.name ?? m.team_b_name,
        color: m.team_b?.color ?? m.team_b_color,
        logo_url: m.team_b?.logo_url ?? null,
        score: m.team_b_score,
    };
}

type Paginated<T> = { data: T[]; next_page_url: string | null };

type Props = { club: Club; matches: Paginated<FootballMatch>; filter: string };
const props = defineProps<Props>();
const { isAdmin } = useClubPermissions();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubes', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
    { title: 'Partidos', href: `/clubs/${props.club.ulid}/matches` },
];

const tabs = [
    { key: 'all', label: 'Todos' },
    { key: 'upcoming', label: 'Proximos' },
    { key: 'completed', label: 'Finalizados' },
] as const;

function switchTab(tab: string) {
    router.get(`/clubs/${props.club.ulid}/matches`, { filter: tab }, {
        preserveState: false,
    });
}

function formatDate(dateStr: string): string {
    return fmtDate(dateStr, { weekday: 'short', day: 'numeric', month: 'short' });
}

function formatTime(dateStr: string): string {
    return fmtTime(dateStr);
}
</script>

<template>
    <Head :title="`${club.name} - Partidos`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-bold">Partidos</h1>
                <Link v-if="isAdmin" :href="`/clubs/${club.ulid}/matches/create`">
                    <Button><Plus class="mr-2 size-4" />Crear</Button>
                </Link>
            </div>

            <!-- Filter tabs -->
            <div class="mb-6 flex gap-1">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="filter === tab.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-accent'"
                    @click="switchTab(tab.key)"
                >
                    {{ tab.label }}
                </button>
            </div>

            <div v-if="matches.data.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                <p class="text-muted-foreground">No hay partidos.</p>
            </div>

            <InfiniteScroll v-else data="matches" #default="{ loading: fetching }">
                <div class="space-y-3">
                    <Link
                        v-for="m in matches.data"
                        :key="m.ulid"
                        :href="`/clubs/${club.ulid}/matches/${m.ulid}`"
                        class="block overflow-hidden rounded-xl border border-border bg-card transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg"
                    >
                        <div class="flex flex-col gap-1.5 px-4 pt-4 sm:flex-row sm:items-start sm:justify-between sm:gap-2">
                            <h3 class="min-w-0 truncate font-semibold leading-tight sm:flex-1">{{ m.title }}</h3>
                            <span
                                v-if="m.season"
                                class="inline-flex max-w-full items-center gap-1 self-end rounded-full border border-violet-500/40 bg-violet-500/15 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-violet-400 sm:max-w-[55%] sm:shrink-0"
                                :title="m.season.name"
                            >
                                <Trophy class="size-2.5 shrink-0" />
                                <span class="truncate">{{ m.season.name }}</span>
                            </span>
                        </div>

                        <div class="px-4 py-4">
                            <MatchTeamsScore
                                :team-a="teamASide(m)"
                                :team-b="teamBSide(m)"
                                :status="m.status"
                                :is-friendly="m.is_friendly ?? false"
                                :scheduled-at="m.scheduled_at"
                                variant="compact"
                            />
                        </div>

                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-border/50 bg-muted/20 px-4 py-2.5 text-xs text-muted-foreground">
                            <span class="flex items-center gap-1"><CalendarDays class="size-3.5" />{{ formatDate(m.scheduled_at) }}</span>
                            <span class="flex items-center gap-1"><Clock class="size-3.5" />{{ formatTime(m.scheduled_at) }}</span>
                            <span v-if="m.field" class="flex items-center gap-1"><MapPin class="size-3.5" />{{ m.field.name }}</span>
                            <span class="flex items-center gap-1"><Users class="size-3.5" />{{ m.attendances_count ?? 0 }}/{{ m.max_players }}</span>
                            <span
                                v-if="m.is_friendly"
                                class="ml-auto rounded-full border border-amber-500/40 bg-amber-500/10 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-500"
                            >Amistoso</span>
                        </div>
                    </Link>
                </div>
                <div v-if="fetching" class="py-4 text-center text-sm text-muted-foreground">
                    Cargando mas partidos...
                </div>
            </InfiniteScroll>
        </div>
    </AppLayout>
</template>
