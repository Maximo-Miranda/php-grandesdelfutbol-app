<script setup lang="ts">
import type { FootballMatch } from '@/types';

const props = defineProps<{
    match: FootballMatch;
    clockDisplay: string;
    teamAGoals: number;
    teamBGoals: number;
}>();

const isLive = props.match.status === 'in_progress';
const isCompleted = props.match.status === 'completed';
</script>

<template>
    <div
        class="relative overflow-hidden rounded-2xl p-4 shadow-lg"
        :class="isLive
            ? 'bg-gradient-to-b from-emerald-950 via-emerald-950/90 to-zinc-950'
            : 'bg-gradient-to-b from-zinc-900 via-zinc-900 to-zinc-950'"
    >
        <!-- Pitch lines -->
        <div class="pointer-events-none absolute inset-0" :class="isLive ? 'opacity-[0.08]' : 'opacity-[0.03]'">
            <div class="absolute top-1/2 left-1/2 size-28 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white"></div>
            <div class="absolute inset-y-0 left-1/2 w-px bg-white"></div>
            <!-- Penalty areas -->
            <div class="absolute top-1/4 bottom-1/4 left-0 w-12 border-r-2 border-y-2 border-white rounded-r-sm"></div>
            <div class="absolute top-1/4 bottom-1/4 right-0 w-12 border-l-2 border-y-2 border-white rounded-l-sm"></div>
        </div>

        <!-- Stadium lights glow (only when live) -->
        <div v-if="isLive" class="pointer-events-none absolute inset-0">
            <div class="absolute -top-8 left-1/4 size-32 rounded-full bg-emerald-400/10 blur-3xl animate-pulse" style="animation-duration: 3s"></div>
            <div class="absolute -top-8 right-1/4 size-32 rounded-full bg-emerald-400/10 blur-3xl animate-pulse" style="animation-duration: 4s"></div>
        </div>

        <div class="relative">
            <!-- Status + Live indicator -->
            <div class="flex items-center justify-center gap-2">
                <span
                    v-if="isLive"
                    class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/40 bg-emerald-500/20 px-3 py-1 text-[10px] font-bold tracking-widest text-emerald-400 uppercase"
                >
                    <span class="relative flex size-2">
                        <span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex size-2 rounded-full bg-emerald-400"></span>
                    </span>
                    EN VIVO
                </span>
                <span
                    v-else-if="isCompleted"
                    class="inline-block rounded-full border border-zinc-600/40 bg-zinc-800/60 px-3 py-1 text-[10px] font-bold tracking-widest text-zinc-400 uppercase"
                >
                    FINALIZADO
                </span>
                <span
                    v-else
                    class="inline-block rounded-full border border-zinc-600/40 bg-zinc-800/60 px-3 py-1 text-[10px] font-bold tracking-widest text-zinc-500 uppercase"
                >
                    {{ match.status === 'upcoming' ? 'PROXIMO' : 'CANCELADO' }}
                </span>
            </div>

            <!-- Score -->
            <div class="mt-3 flex items-center justify-center gap-3 sm:gap-6">
                <div class="min-w-0 flex-1 text-right">
                    <div class="mb-1 flex items-center justify-end gap-1.5">
                        <p class="truncate text-[10px] font-bold tracking-wider uppercase sm:text-xs" :class="isLive ? 'text-emerald-300/70' : 'text-zinc-400'">{{ match.team_a_name }}</p>
                        <span v-if="match.team_a_color" class="size-2.5 shrink-0 rounded-full" :style="{ backgroundColor: match.team_a_color }"></span>
                    </div>
                    <p class="text-5xl font-black tabular-nums text-white sm:text-6xl">{{ teamAGoals }}</p>
                </div>

                <div class="flex flex-col items-center gap-1.5">
                    <span class="text-lg font-light select-none" :class="isLive ? 'text-emerald-600' : 'text-zinc-700'">vs</span>
                    <span
                        v-if="isLive"
                        class="inline-flex items-center gap-1 rounded-full bg-emerald-500/20 px-3 py-1 text-sm font-bold tabular-nums tracking-wider text-emerald-400 font-mono"
                    >
                        {{ clockDisplay }}
                    </span>
                </div>

                <div class="min-w-0 flex-1 text-left">
                    <div class="mb-1 flex items-center gap-1.5">
                        <span v-if="match.team_b_color" class="size-2.5 shrink-0 rounded-full" :style="{ backgroundColor: match.team_b_color }"></span>
                        <p class="truncate text-[10px] font-bold tracking-wider uppercase sm:text-xs" :class="isLive ? 'text-emerald-300/70' : 'text-zinc-400'">{{ match.team_b_name }}</p>
                    </div>
                    <p class="text-5xl font-black tabular-nums text-white sm:text-6xl">{{ teamBGoals }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
