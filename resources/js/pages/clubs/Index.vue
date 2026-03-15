<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, ChevronRight, Clock, Handshake, Plus, Shield, Trophy, UsersRound } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, ClubInvitation, ClubMember, FootballMatch } from '@/types';

type Props = {
    clubs: Club[];
    nextMatch?: FootballMatch | null;
    pendingInvitations?: ClubInvitation[];
    pendingMemberships?: ClubMember[];
};

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mis Clubes', href: '/clubs' },
];

function formatDate(dateStr: string): string {
    const d = new Date(dateStr);
    return d.toLocaleDateString('es', { weekday: 'short', day: 'numeric', month: 'short' })
        + ' a las '
        + d.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Head title="Mis Clubes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Mis Clubes</h1>
                    <p class="text-sm text-muted-foreground">Administra tus clubes de futbol</p>
                </div>
                <Link href="/clubs/create">
                    <Button size="sm">
                        <Plus class="mr-1 size-4" />
                        Crear
                    </Button>
                </Link>
            </div>

            <!-- Pending invitations -->
            <Link
                v-for="inv in pendingInvitations"
                :key="inv.id"
                :href="`/clubs/invitations/${inv.token}/accept`"
                class="mb-3 flex items-center gap-3 rounded-lg border border-primary/30 bg-primary/5 p-4 transition-colors hover:bg-primary/10"
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

            <!-- Pending memberships -->
            <div
                v-for="membership in pendingMemberships"
                :key="membership.id"
                class="mb-3 flex items-center gap-3 rounded-lg border border-yellow-300/50 bg-yellow-50/50 p-4 dark:border-yellow-700/50 dark:bg-yellow-950/20"
            >
                <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-yellow-500/10 text-yellow-600 dark:text-yellow-400">
                    <Clock class="size-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">{{ membership.club?.name }}</p>
                    <p class="text-sm text-muted-foreground">Esperando aprobacion del admin</p>
                </div>
            </div>

            <!-- Next match -->
            <Link
                v-if="nextMatch"
                :href="`/clubs/${nextMatch.club?.ulid}/matches/${nextMatch.ulid}`"
                class="mb-6 block rounded-lg border border-border p-4 transition-colors hover:bg-accent"
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

            <!-- Clubs list -->
            <div v-if="clubs.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                <Shield class="mx-auto mb-3 size-10 text-muted-foreground" />
                <p class="font-medium">Aun no tienes clubes</p>
                <p class="mb-4 text-sm text-muted-foreground">Crea tu primer club para empezar</p>
                <Link href="/clubs/create">
                    <Button variant="outline">Crear mi primer club</Button>
                </Link>
            </div>

            <div v-else class="space-y-2">
                <Link
                    v-for="club in clubs"
                    :key="club.id"
                    :href="`/clubs/${club.ulid}`"
                    class="flex items-center gap-3 rounded-xl border border-border/60 bg-gradient-to-r from-card to-card/60 p-3 transition-all hover:border-primary/40 hover:shadow-md hover:shadow-primary/5"
                >
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
                                <UsersRound class="size-3" />
                                {{ club.members_count }} miembros
                            </span>
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
    </AppLayout>
</template>
