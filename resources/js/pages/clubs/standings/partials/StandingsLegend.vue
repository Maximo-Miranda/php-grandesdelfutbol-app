<script setup lang="ts">
import { STAT_STYLES } from './statStyles';

const stats = [
    { code: 'PJ', label: 'Partidos Jugados' },
    { code: 'G', label: 'Ganados' },
    { code: 'E', label: 'Empatados' },
    { code: 'P', label: 'Perdidos' },
    { code: 'GF', label: 'Goles a Favor' },
    { code: 'GC', label: 'Goles en Contra' },
    { code: 'DG', label: 'Diferencia de Goles' },
    { code: 'Pts', label: 'Puntos' },
] as const;

const results = [
    { letter: 'V', cls: 'bg-emerald-500/15 text-emerald-500 border-emerald-500/40', label: 'Victoria' },
    { letter: 'E', cls: 'bg-amber-500/15 text-amber-500 border-amber-500/40', label: 'Empate' },
    { letter: 'D', cls: 'bg-rose-500/15 text-rose-500 border-rose-500/40', label: 'Derrota' },
    { letter: 'A', cls: 'bg-muted text-muted-foreground border-border', label: 'Amistoso' },
];
</script>

<template>
    <div class="mt-4 rounded-lg border border-border/60 bg-muted/20 p-3 text-xs">
        <p class="mb-2 font-semibold uppercase tracking-wider text-muted-foreground">Referencia</p>

        <dl class="grid grid-cols-2 gap-x-4 gap-y-1.5 sm:grid-cols-4">
            <div v-for="s in stats" :key="s.code" class="flex items-center gap-1.5">
                <dt
                    class="inline-flex min-w-[2rem] justify-center rounded border px-1 py-0.5 font-bold"
                    :class="STAT_STYLES[s.code].chip"
                >{{ s.code }}</dt>
                <dd class="truncate text-muted-foreground">{{ s.label }}</dd>
            </div>
        </dl>

        <div class="mt-3 border-t border-border/60 pt-2">
            <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Últimos 5</p>
            <div class="flex flex-wrap gap-3">
                <div v-for="r in results" :key="r.label" class="flex items-center gap-1.5">
                    <span
                        class="flex size-5 items-center justify-center rounded-full border text-[10px] font-black"
                        :class="r.cls"
                        aria-hidden="true"
                    >
                        {{ r.letter }}
                    </span>
                    <span class="text-muted-foreground">{{ r.label }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
