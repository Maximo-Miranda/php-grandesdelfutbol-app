<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Bell, Cake, CalendarDays, Check, Clock, Copy, LinkIcon, LogOut, MapPin, Settings, UserPlus, UsersRound } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { roleBadgeClass, useClubPermissions } from '@/composables/useClubPermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatTime } from '@/lib/utils';
import type { BreadcrumbItem, Club, ClubMember, FootballMatch } from '@/types';

type BirthdayMember = {
    name: string;
    photo_url: string | null;
    day: number;
};

type Props = {
    club: Club & { members: ClubMember[]; members_count: number; pending_members_count: number; players_count: number; completed_matches_count: number };
    nextMatch?: FootballMatch & { attendances_count?: number };
    lastMatch?: FootballMatch & { attendances_count?: number };
    birthdays: BirthdayMember[];
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubes', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
];

const base = `/clubs/${props.club.ulid}`;

const { role, roleDisplay, isAdmin, isOwner } = useClubPermissions();

const joinUrl = computed(() => `${window.location.origin}/join/${props.club.slug}`);
const copied = ref(false);

function copyJoinLink() {
    navigator.clipboard.writeText(joinUrl.value);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
}

// --- Leave club ---
const showLeaveDialog = ref(false);
const leavingClub = ref(false);

function leaveClub() {
    leavingClub.value = true;
    router.post(`/clubs/${props.club.ulid}/leave`, {}, {
        onFinish: () => { leavingClub.value = false; },
    });
}

const confirmedCount = computed(() => {
    if (!props.nextMatch?.attendances) return 0;
    return props.nextMatch.attendances.filter(a => a.status === 'confirmed').length;
});

function formatMatchDate(dateStr: string): string {
    return formatDate(dateStr, { weekday: 'long', day: 'numeric', month: 'long' });
}

function formatMatchTime(dateStr: string): string {
    return formatTime(dateStr, { hour12: true });
}

const currentMonthName = computed(() => new Date().toLocaleDateString('es', { month: 'long' }));
</script>

<template>
    <Head :title="club.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <!-- Club header -->
            <div class="mb-6 flex items-center gap-4">
                <div
                    v-if="club.logo_url"
                    class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-full bg-muted"
                >
                    <img :src="club.logo_url" :alt="club.name" class="size-full object-cover" />
                </div>
                <div
                    v-else
                    class="flex size-14 shrink-0 items-center justify-center rounded-full bg-muted text-xl font-bold text-muted-foreground"
                >
                    {{ club.name.charAt(0) }}
                </div>
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold">
                        {{ club.name }}
                        <Badge variant="outline" :class="['text-[10px]', roleBadgeClass(role ?? 'player')]">{{ roleDisplay }}</Badge>
                    </h1>
                    <p v-if="club.description" class="text-sm text-muted-foreground">{{ club.description }}</p>
                </div>
            </div>

            <!-- Join link -->
            <div v-if="club.slug" class="mb-4 flex items-center gap-3 rounded-lg border border-border p-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <LinkIcon class="size-4" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium text-muted-foreground">Link de ingreso al club</p>
                    <p class="truncate text-sm">{{ joinUrl }}</p>
                </div>
                <Button variant="outline" size="sm" class="shrink-0 gap-1.5" @click="copyJoinLink">
                    <Check v-if="copied" class="size-3.5" />
                    <Copy v-else class="size-3.5" />
                    {{ copied ? 'Copiado' : 'Copiar' }}
                </Button>
            </div>

            <!-- Next match card -->
            <Link
                v-if="nextMatch"
                :href="`${base}/matches/${nextMatch.ulid}`"
                class="mb-4 block rounded-lg border border-border p-4 transition-colors hover:bg-accent"
            >
                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-primary">Proximo partido</p>
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

            <div v-else class="mb-4 rounded-lg border border-border p-4 text-center">
                <CalendarDays class="mx-auto mb-2 size-8 text-muted-foreground" />
                <p class="font-medium">Sin partidos proximos</p>
                <p class="text-sm text-muted-foreground">Crea un partido para empezar a organizar.</p>
            </div>

            <!-- Last match card -->
            <Link
                v-if="lastMatch"
                :href="`${base}/matches/${lastMatch.ulid}/summary`"
                class="mb-4 block rounded-lg border border-border p-4 transition-colors hover:bg-accent"
            >
                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Ultimo partido</p>
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

            <!-- Birthdays this month -->
            <div class="mb-4 overflow-hidden rounded-lg border border-border">
                <div class="flex items-center gap-2 border-b border-border bg-gradient-to-r from-amber-500/10 to-yellow-500/5 px-4 py-2.5">
                    <Cake class="size-4 text-amber-500" />
                    <h2 class="text-sm font-semibold tracking-wide">
                        Cumpleaños de <span class="capitalize">{{ currentMonthName }}</span>
                    </h2>
                </div>
                <div v-if="birthdays.length > 0" class="divide-y divide-border">
                    <div
                        v-for="(b, i) in birthdays"
                        :key="i"
                        class="flex items-center gap-3 px-4 py-2.5"
                    >
                        <div
                            v-if="b.photo_url"
                            class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-full ring-2 ring-amber-500/30"
                        >
                            <img :src="b.photo_url" :alt="b.name" class="size-full object-cover" />
                        </div>
                        <div
                            v-else
                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-amber-500/10 text-sm font-bold text-amber-600 ring-2 ring-amber-500/30"
                        >
                            {{ b.name.charAt(0) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ b.name }}</p>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <Cake class="size-4 text-amber-500" />
                            <span class="text-sm font-semibold text-primary">{{ b.day }}</span>
                        </div>
                    </div>
                </div>
                <div v-else class="px-4 py-4 text-center">
                    <p class="text-sm text-muted-foreground">Sin cumpleaños este mes</p>
                </div>
            </div>

            <!-- Quick nav cards - full width on mobile, 2 cols on md+ -->
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <Link
                    :href="`${base}/players`"
                    class="flex items-center gap-4 rounded-lg border border-border p-4 transition-colors hover:bg-accent"
                >
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <UsersRound class="size-5" />
                    </div>
                    <div>
                        <p class="font-semibold">Jugadores</p>
                        <p class="text-sm text-muted-foreground">{{ club.players_count ?? 0 }} en el club</p>
                    </div>
                </Link>

                <Link
                    :href="`${base}/matches`"
                    class="flex items-center gap-4 rounded-lg border border-border p-4 transition-colors hover:bg-accent"
                >
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 text-blue-500">
                        <CalendarDays class="size-5" />
                    </div>
                    <div>
                        <p class="font-semibold">Partidos</p>
                        <p class="text-sm text-muted-foreground">{{ club.completed_matches_count ?? 0 }} jugados</p>
                    </div>
                </Link>

                <Link
                    v-if="isAdmin"
                    :href="`${base}/members`"
                    class="relative flex items-center gap-4 rounded-lg border p-4 transition-colors hover:bg-accent"
                    :class="club.pending_members_count > 0 ? 'border-yellow-500/40 bg-yellow-500/5' : 'border-border'"
                >
                    <div class="relative flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <UserPlus class="size-5" />
                        <span
                            v-if="club.pending_members_count > 0"
                            class="absolute -top-1 -right-1 flex size-5 items-center justify-center rounded-full bg-yellow-500 text-[10px] font-bold text-black"
                        >
                            {{ club.pending_members_count }}
                        </span>
                    </div>
                    <div>
                        <p class="font-semibold">Miembros</p>
                        <p v-if="club.pending_members_count > 0" class="text-sm font-medium text-yellow-500">
                            {{ club.pending_members_count }} solicitud{{ club.pending_members_count > 1 ? 'es' : '' }} pendiente{{ club.pending_members_count > 1 ? 's' : '' }}
                        </p>
                        <p v-else class="text-sm text-muted-foreground">Gestionar miembros del club</p>
                    </div>
                </Link>

                <Link
                    :href="`${base}/notifications`"
                    class="flex items-center gap-4 rounded-lg border border-border p-4 transition-colors hover:bg-accent"
                >
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500">
                        <Bell class="size-5" />
                    </div>
                    <div>
                        <p class="font-semibold">Notificaciones</p>
                        <p class="text-sm text-muted-foreground">Push y alertas del club</p>
                    </div>
                </Link>

                <Link
                    v-if="isAdmin"
                    :href="`${base}/edit`"
                    class="flex items-center gap-4 rounded-lg border border-border p-4 transition-colors hover:bg-accent"
                >
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                        <Settings class="size-5" />
                    </div>
                    <div>
                        <p class="font-semibold">Configuración</p>
                        <p class="text-sm text-muted-foreground">Logo, acceso, invitación</p>
                    </div>
                </Link>
            </div>

            <!-- Leave club -->
            <div v-if="!isOwner" class="mt-6 flex justify-center">
                <Button variant="outline" class="text-destructive" @click="showLeaveDialog = true">
                    <LogOut class="mr-2 size-4" />
                    Salir del club
                </Button>
            </div>
        </div>

        <!-- Leave club dialog -->
        <ConfirmDialog
            v-model:open="showLeaveDialog"
            title="Salir del club"
            description="Esta acción no se puede deshacer. Perderás acceso al club y sus partidos."
            confirm-label="Salir del club"
            :destructive="true"
            :processing="leavingClub"
            @confirm="leaveClub"
        />
    </AppLayout>
</template>
