<script setup lang="ts">
import { Clock, Radio, Shield } from 'lucide-vue-next';
import { computed } from 'vue';
import { contrastTextColor, formatTime, teamInitials } from '@/lib/utils';
import type { MatchStatus, TeamSide } from '@/types';

const props = withDefaults(defineProps<{
    teamA: TeamSide;
    teamB: TeamSide | null;
    status: MatchStatus;
    isFriendly: boolean;
    scheduledAt: string;
    variant?: 'default' | 'compact';
}>(), {
    variant: 'default',
});

const size = computed(() => props.variant === 'compact' ? {
    crest: 'size-11 sm:size-12',
    initials: 'text-sm sm:text-base',
    score: 'text-2xl sm:text-3xl',
    name: 'text-xs sm:text-sm',
    gap: 'gap-1.5 sm:gap-2',
} : {
    crest: 'size-14 sm:size-16',
    initials: 'text-base sm:text-lg',
    score: 'text-3xl sm:text-4xl',
    name: 'text-xs sm:text-sm',
    gap: 'gap-2',
});

const outcome = computed<'a' | 'b' | 'draw' | null>(() => {
    if (props.status !== 'completed' || props.isFriendly) return null;
    const a = props.teamA.score;
    const b = props.teamB?.score;
    if (a === null || b === null || b === undefined) return null;
    if (a > b) return 'a';
    if (b > a) return 'b';
    return 'draw';
});
</script>

<template>
    <div class="grid grid-cols-[1fr_auto_1fr] items-start gap-3 sm:gap-5">
        <!-- Team A: crest on top, name below -->
        <div
            class="flex flex-col items-center text-center"
            :class="[size.gap, outcome === 'b' ? 'opacity-60' : '']"
        >
            <div
                class="grid place-items-center overflow-hidden rounded-full ring-1 ring-border/60"
                :class="size.crest"
                :style="teamA.logo_url ? { background: 'var(--background)' } : { background: teamA.color ?? '#71717a' }"
            >
                <img
                    v-if="teamA.logo_url"
                    :src="teamA.logo_url"
                    :alt="teamA.name"
                    class="size-full object-cover"
                />
                <span
                    v-else
                    class="font-black tracking-tight"
                    :class="size.initials"
                    :style="{ color: contrastTextColor(teamA.color) }"
                >{{ teamInitials(teamA.name) }}</span>
            </div>
            <div>
                <p
                    class="line-clamp-2 font-bold leading-tight"
                    :class="[size.name, outcome === 'b' ? 'text-muted-foreground' : 'text-foreground']"
                >{{ teamA.name }}</p>
                <p v-if="outcome === 'a'" class="mt-0.5 text-[9px] font-semibold uppercase tracking-wider text-emerald-500">Ganador</p>
            </div>
        </div>

        <!-- Center: score / vs -->
        <div class="flex shrink-0 flex-col items-center justify-center gap-1 self-center">
            <template v-if="status === 'completed' && teamB">
                <div class="flex items-center gap-2 sm:gap-3">
                    <span
                        class="font-black tabular-nums"
                        :class="[size.score, outcome === 'b' ? 'text-muted-foreground/60' : 'text-foreground']"
                    >{{ teamA.score ?? 0 }}</span>
                    <span class="text-xs font-bold uppercase tracking-widest text-muted-foreground/70">vs</span>
                    <span
                        class="font-black tabular-nums"
                        :class="[size.score, outcome === 'a' ? 'text-muted-foreground/60' : 'text-foreground']"
                    >{{ teamB.score ?? 0 }}</span>
                </div>
                <span class="rounded-full border border-blue-500/40 bg-blue-500/15 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-blue-500">Finalizado</span>
            </template>

            <template v-else-if="status === 'in_progress'">
                <div class="flex items-center gap-2 sm:gap-3">
                    <span class="font-black tabular-nums" :class="size.score">{{ teamA.score ?? 0 }}</span>
                    <span class="text-xs font-bold uppercase tracking-widest text-muted-foreground/70">vs</span>
                    <span class="font-black tabular-nums" :class="size.score">{{ teamB?.score ?? 0 }}</span>
                </div>
                <span class="flex items-center gap-1 rounded-full border border-orange-500/40 bg-orange-500/15 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-orange-500">
                    <Radio class="size-2.5 animate-pulse" />En vivo
                </span>
            </template>

            <template v-else-if="status === 'cancelled'">
                <span class="text-2xl font-bold uppercase tracking-widest text-muted-foreground/70 sm:text-3xl">vs</span>
                <span class="rounded-full border border-zinc-500/40 bg-zinc-500/15 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-zinc-500">Cancelado</span>
            </template>

            <template v-else>
                <span class="text-2xl font-bold uppercase tracking-widest text-muted-foreground sm:text-3xl">vs</span>
                <span class="flex items-center gap-1 text-xs font-semibold tabular-nums text-foreground">
                    <Clock class="size-3 text-muted-foreground" />
                    {{ formatTime(scheduledAt) }}
                </span>
                <span class="rounded-full border border-emerald-500/40 bg-emerald-500/15 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-emerald-500">Próximo</span>
            </template>
        </div>

        <!-- Team B: crest on top, name below -->
        <div
            v-if="teamB"
            class="flex flex-col items-center text-center"
            :class="[size.gap, outcome === 'a' ? 'opacity-60' : '']"
        >
            <div
                class="grid place-items-center overflow-hidden rounded-full ring-1 ring-border/60"
                :class="size.crest"
                :style="teamB.logo_url ? { background: 'var(--background)' } : { background: teamB.color ?? '#71717a' }"
            >
                <img
                    v-if="teamB.logo_url"
                    :src="teamB.logo_url"
                    :alt="teamB.name"
                    class="size-full object-cover"
                />
                <span
                    v-else
                    class="font-black tracking-tight"
                    :class="size.initials"
                    :style="{ color: contrastTextColor(teamB.color) }"
                >{{ teamInitials(teamB.name) }}</span>
            </div>
            <div>
                <p
                    class="line-clamp-2 font-bold leading-tight"
                    :class="[size.name, outcome === 'a' ? 'text-muted-foreground' : 'text-foreground']"
                >{{ teamB.name }}</p>
                <p v-if="outcome === 'b'" class="mt-0.5 text-[9px] font-semibold uppercase tracking-wider text-emerald-500">Ganador</p>
            </div>
        </div>

        <div v-else class="flex flex-col items-center text-center text-muted-foreground" :class="size.gap">
            <div
                class="grid place-items-center rounded-full border border-dashed border-border/70"
                :class="size.crest"
            >
                <Shield class="size-5 opacity-40" />
            </div>
            <p class="text-xs italic">Rival externo</p>
        </div>
    </div>
</template>
