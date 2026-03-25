<script setup lang="ts">
import { Head, InfiniteScroll, Link } from '@inertiajs/vue3';
import { CalendarDays, ChevronRight, Clock, Handshake, MapPin, Plus, Shield, Trophy, UsersRound } from 'lucide-vue-next';
import { computed } from 'vue';
import ClubShield from '@/components/ClubShield.vue';
import VideoServiceCta from '@/components/VideoServiceCta.vue';
import VideoServiceRequestDialog from '@/components/VideoServiceRequestDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useVideoServiceRequest } from '@/composables/useVideoServiceRequest';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatTime } from '@/lib/utils';
import type { BreadcrumbItem, Club, ClubInvitation, ClubMember, FootballMatch } from '@/types';

type PaginatedClubs = {
    data: Club[];
};

type Props = {
    clubs: PaginatedClubs;
    nextMatch?: (FootballMatch & { confirmed_count?: number }) | null;
    lastMatch?: FootballMatch | null;
    pendingInvitations?: ClubInvitation[];
    pendingMemberships?: ClubMember[];
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mis Clubes', href: '/clubs' },
];

function formatMatchDate(dateStr: string): string {
    return formatDate(dateStr, { weekday: 'long', day: 'numeric', month: 'long' });
}

function formatMatchTime(dateStr: string): string {
    return formatTime(dateStr, { hour12: true });
}

const confirmedCount = computed(() => props.nextMatch?.confirmed_count ?? 0);

const vsr = useVideoServiceRequest();

function openVsr(): void {
    vsr.open({
        clubName: props.nextMatch?.club?.name ?? '',
        fieldName: props.nextMatch?.field?.name,
        scheduledAt: props.nextMatch?.scheduled_at,
        matchUlid: props.nextMatch?.ulid,
    });
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
                <div class="mb-2 flex items-center gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-primary">Proximo partido</p>
                    <Badge v-if="nextMatch.club" variant="secondary" class="text-[10px]">{{ nextMatch.club.name }}</Badge>
                </div>
                <p class="text-lg font-bold">{{ nextMatch.title }}</p>
                <div class="mt-2 flex flex-col gap-1.5">
                    <div class="flex items-center gap-2 text-sm text-muted-foreground">
                        <CalendarDays class="size-3.5 shrink-0" />
                        <span class="capitalize">{{ formatMatchDate(nextMatch.scheduled_at) }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-muted-foreground">
                        <Clock class="size-3.5 shrink-0" />
                        <span>{{ formatMatchTime(nextMatch.scheduled_at) }}</span>
                    </div>
                    <div v-if="nextMatch.field" class="flex items-center gap-2 text-sm text-muted-foreground">
                        <MapPin class="size-3.5 shrink-0" />
                        <span>{{ nextMatch.field.name }}</span>
                    </div>
                </div>
                <p class="mt-2 text-sm font-medium text-primary">
                    {{ confirmedCount }}/{{ nextMatch.max_players }} confirmados
                </p>
            </Link>

            <VideoServiceCta
                v-if="nextMatch"
                class="mb-6"
                compact
                :status="nextMatch.active_video_service_request?.status"
                @request="openVsr"
            />

            <!-- Last match -->
            <Link
                v-if="lastMatch"
                :href="`/clubs/${lastMatch.club?.ulid}/matches/${lastMatch.ulid}/summary`"
                class="mb-6 block rounded-lg border border-border p-4 transition-colors hover:bg-accent"
            >
                <div class="mb-2 flex items-center gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Ultimo partido</p>
                    <Badge v-if="lastMatch.club" variant="secondary" class="text-[10px]">{{ lastMatch.club.name }}</Badge>
                </div>
                <p class="text-lg font-bold">{{ lastMatch.title }}</p>
                <div class="mt-2 flex flex-col gap-1.5">
                    <div class="flex items-center gap-2 text-sm text-muted-foreground">
                        <CalendarDays class="size-3.5 shrink-0" />
                        <span class="capitalize">{{ formatMatchDate(lastMatch.scheduled_at) }}</span>
                    </div>
                    <div v-if="lastMatch.field" class="flex items-center gap-2 text-sm text-muted-foreground">
                        <MapPin class="size-3.5 shrink-0" />
                        <span>{{ lastMatch.field.name }}</span>
                    </div>
                </div>
                <div class="mt-2 flex items-center gap-3 text-sm">
                    <span class="text-muted-foreground">{{ lastMatch.attendances_count ?? 0 }} jugadores</span>
                    <span v-if="lastMatch.video_upload?.youtube_video_id" class="text-primary">Video disponible</span>
                </div>
            </Link>

            <!-- Clubs list -->
            <div v-if="clubs.data.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                <Shield class="mx-auto mb-3 size-10 text-muted-foreground" />
                <p class="font-medium">Aun no tienes clubes</p>
                <p class="mb-4 text-sm text-muted-foreground">Crea tu primer club para empezar</p>
                <Link href="/clubs/create">
                    <Button variant="outline">Crear mi primer club</Button>
                </Link>
            </div>

            <InfiniteScroll v-else data="clubs" only-next preserve-url>
                <template #default>
                    <div class="space-y-2">
                        <Link
                            v-for="club in clubs.data"
                            :key="club.id"
                            :href="`/clubs/${club.ulid}`"
                            class="flex items-center gap-3 rounded-xl border border-border/60 bg-gradient-to-r from-card to-card/60 p-3 transition-all hover:border-primary/40 hover:shadow-md hover:shadow-primary/5"
                        >
                            <div v-if="club.logo_url" class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-border bg-muted">
                                <img :src="club.logo_url" :alt="club.name" class="size-full object-cover" />
                            </div>
                            <ClubShield v-else :name="club.name" :size="40" />
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
                </template>

                <template #loading>
                    <div class="space-y-2 py-2">
                        <div v-for="i in 3" :key="i" class="animate-pulse rounded-xl border border-border/60 p-3">
                            <div class="flex items-center gap-3">
                                <div class="size-10 rounded-full bg-muted" />
                                <div class="flex-1">
                                    <div class="mb-1.5 h-4 w-32 rounded bg-muted" />
                                    <div class="h-3 w-48 rounded bg-muted" />
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </InfiniteScroll>
        </div>

        <VideoServiceRequestDialog
            v-model:open="vsr.showDialog.value"
            v-model:plan="vsr.plan.value"
            v-model:phone="vsr.phone.value"
            v-model:message="vsr.message.value"
            :errors="vsr.errors.value"
            :submitting="vsr.submitting.value"
            :success="vsr.success.value"
            @submit="vsr.submit"
            @close="vsr.close"
        />
    </AppLayout>
</template>
