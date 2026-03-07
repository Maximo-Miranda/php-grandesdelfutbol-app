<script setup lang="ts">
import { Head, InfiniteScroll, Link, WhenVisible } from '@inertiajs/vue3';
import { CalendarDays, ChevronRight, Clock, Goal, Handshake, Shield, Trophy, UsersRound } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem, Club, ClubInvitation, ClubMember, FootballMatch } from '@/types';

type PlayerStats = {
    goals: number;
    assists: number;
    matches: number;
    yellowCards: number;
    redCards: number;
};

type PaginatedMatches = {
    data: FootballMatch[];
};

type Props = {
    topClubs: Club[];
    playerStats: PlayerStats;
    upcomingMatches: PaginatedMatches;
    recentMatches?: FootballMatch[];
    pendingInvitations: ClubInvitation[];
    pendingMemberships: ClubMember[];
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: dashboard() }];

const userName = computed(() => {
    const page = (window as any).__page;
    return page?.props?.auth?.user?.name ?? '';
});

const nextMatch = computed(() => props.upcomingMatches?.data?.[0] ?? null);

function formatDate(dateStr: string): string {
    const d = new Date(dateStr);
    return d.toLocaleDateString('es', { weekday: 'short', day: 'numeric', month: 'short' })
        + ' a las '
        + d.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' });
}

function formatShortDate(dateStr: string): string {
    const d = new Date(dateStr);
    return d.toLocaleDateString('es', { day: 'numeric', month: 'short' });
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <!-- Greeting -->
            <h1 class="text-2xl font-bold">Hola, {{ userName }}</h1>
            <p class="mt-1 text-sm text-muted-foreground">Resumen de tu actividad</p>

            <!-- Pending invitations -->
            <Link
                v-for="inv in pendingInvitations"
                :key="inv.id"
                :href="`/clubs/invitations/${inv.token}/accept`"
                class="mt-4 flex items-center gap-3 rounded-lg border border-primary/30 bg-primary/5 p-4 transition-colors hover:bg-primary/10"
            >
                <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <Handshake class="size-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">Invitacion a {{ inv.club?.name }}</p>
                    <p class="text-sm text-muted-foreground">Toca para aceptar o rechazar</p>
                </div>
                <ChevronRight class="size-5 shrink-0 text-muted-foreground" />
            </Link>

            <!-- Pending memberships (waiting for admin approval) -->
            <div
                v-for="membership in pendingMemberships"
                :key="membership.id"
                class="mt-4 flex items-center gap-3 rounded-lg border border-yellow-300/50 bg-yellow-50/50 p-4 dark:border-yellow-700/50 dark:bg-yellow-950/20"
            >
                <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-yellow-500/10 text-yellow-600 dark:text-yellow-400">
                    <Clock class="size-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">{{ membership.club?.name }}</p>
                    <p class="text-sm text-muted-foreground">Esperando aprobacion del admin</p>
                </div>
            </div>

            <!-- Next match (featured) -->
            <div class="mt-6">
                <Link
                    v-if="nextMatch"
                    :href="`/clubs/${nextMatch.club?.ulid}/matches/${nextMatch.ulid}`"
                    class="block rounded-lg border border-border p-4 transition-colors hover:bg-accent"
                >
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-primary">Proximo partido</p>
                    <p class="text-lg font-bold">{{ nextMatch.title }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ formatDate(nextMatch.scheduled_at) }}
                        <span v-if="nextMatch.field"> &middot; {{ nextMatch.field.name }}</span>
                    </p>
                    <div class="mt-2 flex items-center gap-2">
                        <Badge variant="outline">
                            <UsersRound class="mr-1 size-3" />
                            {{ nextMatch.attendances_count ?? 0 }}/{{ nextMatch.max_players }}
                        </Badge>
                        <Badge v-if="nextMatch.club" variant="secondary">{{ nextMatch.club.name }}</Badge>
                    </div>
                </Link>

                <div v-else class="rounded-lg border border-border p-4 text-center">
                    <CalendarDays class="mx-auto mb-2 size-8 text-muted-foreground" />
                    <p class="font-medium">Sin partidos proximos</p>
                    <p class="text-sm text-muted-foreground">Entra a un club para ver partidos.</p>
                </div>
            </div>

            <!-- Player stats -->
            <div v-if="playerStats.matches > 0" class="mt-6">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-muted-foreground">Mis estadisticas</h2>
                <div class="grid grid-cols-3 gap-3 sm:grid-cols-5">
                    <div class="rounded-lg border border-border p-3 text-center">
                        <Trophy class="mx-auto mb-1 size-5 text-primary" />
                        <p class="text-2xl font-bold">{{ playerStats.matches }}</p>
                        <p class="text-xs text-muted-foreground">Partidos</p>
                    </div>
                    <div class="rounded-lg border border-border p-3 text-center">
                        <Goal class="mx-auto mb-1 size-5 text-green-500" />
                        <p class="text-2xl font-bold">{{ playerStats.goals }}</p>
                        <p class="text-xs text-muted-foreground">Goles</p>
                    </div>
                    <div class="rounded-lg border border-border p-3 text-center">
                        <Handshake class="mx-auto mb-1 size-5 text-blue-500" />
                        <p class="text-2xl font-bold">{{ playerStats.assists }}</p>
                        <p class="text-xs text-muted-foreground">Asistencias</p>
                    </div>
                    <div class="rounded-lg border border-border p-3 text-center">
                        <div class="mx-auto mb-1 size-5 rounded-sm bg-yellow-400"></div>
                        <p class="text-2xl font-bold">{{ playerStats.yellowCards }}</p>
                        <p class="text-xs text-muted-foreground">Amarillas</p>
                    </div>
                    <div class="rounded-lg border border-border p-3 text-center">
                        <div class="mx-auto mb-1 size-5 rounded-sm bg-red-500"></div>
                        <p class="text-2xl font-bold">{{ playerStats.redCards }}</p>
                        <p class="text-xs text-muted-foreground">Rojas</p>
                    </div>
                </div>
            </div>

            <!-- Top active clubs -->
            <div v-if="topClubs.length > 0" class="mt-6">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-muted-foreground">Clubes activos</h2>
                <div class="space-y-2">
                    <Link
                        v-for="(club, index) in topClubs"
                        :key="club.id"
                        :href="`/clubs/${club.ulid}`"
                        class="flex items-center gap-3 rounded-xl border border-border/60 bg-gradient-to-r from-card to-card/60 p-3 transition-all hover:border-primary/40 hover:shadow-md hover:shadow-primary/5"
                    >
                        <div
                            class="flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                            :class="[
                                index === 0 ? 'bg-yellow-500/20 text-yellow-400' : '',
                                index === 1 ? 'bg-gray-300/20 text-gray-400' : '',
                                index === 2 ? 'bg-amber-700/20 text-amber-600' : '',
                            ]"
                        >
                            {{ index + 1 }}
                        </div>
                        <div
                            v-if="club.logo_url"
                            class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-border bg-muted"
                        >
                            <img :src="club.logo_url" :alt="club.name" class="size-full object-cover" />
                        </div>
                        <div
                            v-else
                            class="flex size-10 shrink-0 items-center justify-center rounded-full border-2 border-border bg-muted text-sm font-bold"
                        >
                            {{ club.name.charAt(0) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ club.name }}</p>
                            <div class="flex items-center gap-3 text-xs text-muted-foreground">
                                <span class="flex items-center gap-1">
                                    <Trophy class="size-3" />
                                    {{ club.matches_count ?? 0 }} partidos
                                </span>
                                <span v-if="(club.upcoming_matches_count ?? 0) > 0" class="flex items-center gap-1 text-primary">
                                    <CalendarDays class="size-3" />
                                    {{ club.upcoming_matches_count }} proximos
                                </span>
                            </div>
                        </div>
                        <ChevronRight class="size-4 shrink-0 text-muted-foreground" />
                    </Link>
                </div>
            </div>

            <!-- Upcoming matches (infinite scroll) -->
            <div v-if="upcomingMatches.data.length > 1" class="mt-6">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-muted-foreground">Proximos partidos</h2>
                <InfiniteScroll data="upcomingMatches" only-next preserve-url>
                    <template #default>
                        <div class="space-y-2">
                            <Link
                                v-for="match in upcomingMatches.data.slice(1)"
                                :key="match.id"
                                :href="`/clubs/${match.club?.ulid}/matches/${match.ulid}`"
                                class="flex items-center justify-between rounded-lg border border-border p-3 transition-colors hover:bg-accent"
                            >
                                <div class="min-w-0">
                                    <p class="truncate font-medium">{{ match.title }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ formatDate(match.scheduled_at) }}
                                        <span v-if="match.club"> &middot; {{ match.club.name }}</span>
                                    </p>
                                </div>
                                <Badge variant="outline" class="ml-2 shrink-0">
                                    {{ match.attendances_count ?? 0 }}/{{ match.max_players }}
                                </Badge>
                            </Link>
                        </div>
                    </template>

                    <template #loading>
                        <div class="space-y-2 py-2">
                            <div v-for="i in 3" :key="i" class="animate-pulse rounded-lg border border-border p-3">
                                <div class="mb-1 h-4 w-40 rounded bg-muted"></div>
                                <div class="h-3 w-28 rounded bg-muted"></div>
                            </div>
                        </div>
                    </template>
                </InfiniteScroll>
            </div>

            <!-- Recent matches -->
            <WhenVisible :data="['recentMatches']">
                <template #fallback>
                    <div class="mt-10 border-t border-border pt-8">
                        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-muted-foreground">Ultimos resultados</h2>
                        <div class="space-y-2">
                            <div v-for="i in 3" :key="i" class="animate-pulse rounded-lg border border-border p-3">
                                <div class="mb-1 h-4 w-40 rounded bg-muted"></div>
                                <div class="h-3 w-28 rounded bg-muted"></div>
                            </div>
                        </div>
                    </div>
                </template>

                <div v-if="recentMatches?.length" class="mt-10 border-t border-border pt-8">
                    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-muted-foreground">Ultimos resultados</h2>
                    <div class="space-y-2">
                        <Link
                            v-for="match in recentMatches"
                            :key="match.id"
                            :href="`/clubs/${match.club?.ulid}/matches/${match.ulid}`"
                            class="flex items-center justify-between rounded-lg border border-border p-3 transition-colors hover:bg-accent"
                        >
                            <div class="min-w-0">
                                <p class="truncate font-medium">{{ match.title }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ match.ended_at ? formatShortDate(match.ended_at) : 'Completado' }}
                                    <span v-if="match.club"> &middot; {{ match.club.name }}</span>
                                </p>
                            </div>
                            <Badge variant="secondary" class="ml-2 shrink-0">
                                <Shield class="mr-1 size-3" />
                                completado
                            </Badge>
                        </Link>
                    </div>
                </div>
            </WhenVisible>
        </div>
    </AppLayout>
</template>
