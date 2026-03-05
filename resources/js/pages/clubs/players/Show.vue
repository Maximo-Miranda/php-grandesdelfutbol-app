<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarCheck, Pencil, Target, Trophy, Shirt, SquareIcon } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, Player } from '@/types';

type LastGoal = {
    match_id: number;
    match_title: string;
    match_date: string;
    minute: number;
};

type Props = {
    club: Club;
    player: Player;
    lastGoal: LastGoal | null;
    attendanceRate: number | null;
};
const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.id}` },
    { title: 'Jugadores', href: `/clubs/${props.club.id}/players` },
    { title: props.player.name, href: `/clubs/${props.club.id}/players/${props.player.id}` },
];

const initials = computed(() => {
    return props.player.display_name
        .split(' ')
        .map(w => w[0])
        .join('')
        .substring(0, 2)
        .toUpperCase();
});

const goalsPerMatch = computed(() => {
    if (!props.player.matches_played) return '-';
    return (props.player.goals / props.player.matches_played).toFixed(1);
});

const totalContributions = computed(() => props.player.goals + props.player.assists);

const goalsRatio = computed(() => {
    if (!totalContributions.value) return 0;
    return Math.round((props.player.goals / totalContributions.value) * 100);
});
</script>

<template>
    <Head :title="player.display_name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <!-- Hero Banner -->
            <div class="relative mb-6 overflow-hidden rounded-2xl border border-border bg-gradient-to-br from-card via-card to-primary/10">
                <!-- Jersey number watermark -->
                <div
                    v-if="player.jersey_number"
                    class="absolute -right-4 -top-6 select-none text-[12rem] font-black leading-none text-primary/5"
                >
                    {{ player.jersey_number }}
                </div>

                <div class="relative z-10 flex items-center gap-5 p-6">
                    <!-- Avatar -->
                    <img
                        v-if="player.photo_url"
                        :src="player.photo_url"
                        :alt="player.display_name"
                        class="size-20 shrink-0 rounded-full border-2 border-primary/30 object-cover"
                    />
                    <div v-else class="flex size-20 shrink-0 items-center justify-center rounded-full border-2 border-primary/30 bg-primary/10 text-2xl font-bold text-primary">
                        {{ initials }}
                    </div>

                    <!-- Player info -->
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-3">
                            <h1 class="truncate text-2xl font-extrabold uppercase tracking-tight">
                                {{ player.display_name }}
                            </h1>
                            <Link
                                :href="`/clubs/${club.id}/players/${player.id}/edit`"
                                class="shrink-0 rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                            >
                                <Pencil class="size-4" />
                            </Link>
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span
                                v-if="player.jersey_number"
                                class="inline-flex items-center rounded-md bg-primary/15 px-2 py-0.5 text-xs font-bold text-primary"
                            >
                                #{{ player.jersey_number }}
                            </span>
                            <span
                                v-if="player.position_label"
                                class="inline-flex items-center rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                            >
                                {{ player.position_label }}
                            </span>
                            <span
                                :class="[
                                    'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium',
                                    player.is_active
                                        ? 'bg-emerald-500/15 text-emerald-400'
                                        : 'bg-red-500/15 text-red-400',
                                ]"
                            >
                                <span class="size-1.5 rounded-full" :class="player.is_active ? 'bg-emerald-400' : 'bg-red-400'" />
                                {{ player.is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Subtle bottom accent line -->
                <div class="h-0.5 bg-gradient-to-r from-transparent via-primary/40 to-transparent" />
            </div>

            <!-- Main Stats -->
            <div class="mb-4 grid grid-cols-3 gap-3">
                <div class="group relative overflow-hidden rounded-xl border border-border bg-card p-4 text-center transition-colors hover:border-primary/30">
                    <div class="absolute inset-0 bg-gradient-to-b from-primary/5 to-transparent opacity-0 transition-opacity group-hover:opacity-100" />
                    <div class="relative">
                        <Target class="mx-auto mb-2 size-5 text-primary/60" />
                        <div class="text-3xl font-extrabold tabular-nums text-foreground">
                            {{ player.goals }}
                        </div>
                        <div class="mt-1 text-[11px] font-semibold uppercase tracking-widest text-muted-foreground">
                            Goles
                        </div>
                    </div>
                </div>

                <div class="group relative overflow-hidden rounded-xl border border-border bg-card p-4 text-center transition-colors hover:border-primary/30">
                    <div class="absolute inset-0 bg-gradient-to-b from-primary/5 to-transparent opacity-0 transition-opacity group-hover:opacity-100" />
                    <div class="relative">
                        <Shirt class="mx-auto mb-2 size-5 text-primary/60" />
                        <div class="text-3xl font-extrabold tabular-nums text-foreground">
                            {{ player.assists }}
                        </div>
                        <div class="mt-1 text-[11px] font-semibold uppercase tracking-widest text-muted-foreground">
                            Asist.
                        </div>
                    </div>
                </div>

                <div class="group relative overflow-hidden rounded-xl border border-border bg-card p-4 text-center transition-colors hover:border-primary/30">
                    <div class="absolute inset-0 bg-gradient-to-b from-primary/5 to-transparent opacity-0 transition-opacity group-hover:opacity-100" />
                    <div class="relative">
                        <Trophy class="mx-auto mb-2 size-5 text-primary/60" />
                        <div class="text-3xl font-extrabold tabular-nums text-foreground">
                            {{ player.matches_played }}
                        </div>
                        <div class="mt-1 text-[11px] font-semibold uppercase tracking-widest text-muted-foreground">
                            Partidos
                        </div>
                    </div>
                </div>
            </div>

            <!-- Discipline & Rate -->
            <div class="mb-6 grid grid-cols-3 gap-3">
                <div class="flex items-center justify-center gap-2 rounded-xl border border-border bg-card px-3 py-3">
                    <SquareIcon class="size-3.5 fill-amber-400 text-amber-400" />
                    <span class="text-lg font-bold tabular-nums">{{ player.yellow_cards }}</span>
                    <span class="text-xs text-muted-foreground">Amarillas</span>
                </div>

                <div class="flex items-center justify-center gap-2 rounded-xl border border-border bg-card px-3 py-3">
                    <SquareIcon class="size-3.5 fill-red-500 text-red-500" />
                    <span class="text-lg font-bold tabular-nums">{{ player.red_cards }}</span>
                    <span class="text-xs text-muted-foreground">Rojas</span>
                </div>

                <div class="flex items-center justify-center gap-2 rounded-xl border border-border bg-card px-3 py-3">
                    <template v-if="attendanceRate !== null">
                        <CalendarCheck class="size-3.5 text-primary" />
                        <span class="text-lg font-bold tabular-nums text-primary">{{ attendanceRate }}%</span>
                        <span class="text-xs text-muted-foreground">Asist.</span>
                    </template>
                    <template v-else>
                        <span class="text-lg font-bold tabular-nums text-primary">{{ goalsPerMatch }}</span>
                        <span class="text-xs text-muted-foreground">Goles/PJ</span>
                    </template>
                </div>
            </div>

            <!-- Contributions -->
            <div class="overflow-hidden rounded-xl border border-border bg-card p-5">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                        Contribuciones de gol
                    </h3>
                    <span class="text-2xl font-extrabold tabular-nums text-primary">{{ totalContributions }}</span>
                </div>

                <div v-if="totalContributions > 0" class="mt-4 space-y-3">
                    <!-- Goals bar -->
                    <div class="flex items-center gap-3">
                        <span class="w-14 text-right text-xs font-medium text-muted-foreground">Goles</span>
                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full bg-primary transition-all duration-500"
                                :style="{ width: `${goalsRatio}%` }"
                            />
                        </div>
                        <span class="w-8 text-right text-sm font-bold tabular-nums">{{ player.goals }}</span>
                    </div>
                    <!-- Assists bar -->
                    <div class="flex items-center gap-3">
                        <span class="w-14 text-right text-xs font-medium text-muted-foreground">Asist.</span>
                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full bg-emerald-300/60 transition-all duration-500"
                                :style="{ width: `${100 - goalsRatio}%` }"
                            />
                        </div>
                        <span class="w-8 text-right text-sm font-bold tabular-nums">{{ player.assists }}</span>
                    </div>
                </div>

                <p v-else class="mt-3 text-sm text-muted-foreground">
                    Sin contribuciones aun.
                </p>
            </div>

            <!-- Last Goal -->
            <Link
                v-if="lastGoal"
                :href="`/clubs/${club.id}/matches/${lastGoal.match_id}`"
                class="mt-4 block overflow-hidden rounded-xl border border-border bg-card p-5 transition-colors hover:border-primary/30"
            >
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                    Ultimo gol
                </h3>
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10">
                        <Target class="size-5 text-primary" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ lastGoal.match_title }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ lastGoal.match_date }} &middot; Min {{ lastGoal.minute }}'
                        </p>
                    </div>
                </div>
            </Link>
        </div>
    </AppLayout>
</template>
