<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Trophy } from 'lucide-vue-next';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { Club } from '@/types';
import StandingsLegend from './StandingsLegend.vue';
import { STAT_STYLES  } from './statStyles';
import type {StatCode} from './statStyles';

const headers: Array<{ code: StatCode; full: string }> = [
    { code: 'PJ', full: 'Partidos Jugados' },
    { code: 'G', full: 'Ganados' },
    { code: 'E', full: 'Empatados' },
    { code: 'P', full: 'Perdidos' },
    { code: 'GF', full: 'Goles a Favor' },
    { code: 'GC', full: 'Goles en Contra' },
    { code: 'DG', full: 'Diferencia de Goles' },
    { code: 'Pts', full: 'Puntos' },
];

type StandingRow = {
    team_id: number;
    team_ulid: string;
    name: string;
    color: string;
    logo_url: string | null;
    PJ: number;
    G: number;
    E: number;
    P: number;
    GF: number;
    GC: number;
    DG: number;
    Pts: number;
    last5: Array<'W' | 'D' | 'L' | 'F'>;
};

defineProps<{
    club: Club;
    standings: StandingRow[];
}>();

function last5Variant(code: string): { cls: string; label: string; letter: string } {
    switch (code) {
        case 'W':
            return { cls: 'bg-emerald-500/15 text-emerald-500 border-emerald-500/40', label: 'Victoria', letter: 'V' };
        case 'L':
            return { cls: 'bg-rose-500/15 text-rose-500 border-rose-500/40', label: 'Derrota', letter: 'D' };
        case 'D':
            return { cls: 'bg-amber-500/15 text-amber-500 border-amber-500/40', label: 'Empate', letter: 'E' };
        case 'F':
            return { cls: 'bg-muted text-muted-foreground border-border', label: 'Amistoso', letter: 'A' };
        default:
            return { cls: 'bg-transparent text-muted-foreground/40 border-border', label: 'Sin partido', letter: '-' };
    }
}
</script>

<template>
    <div v-if="standings.length === 0" class="rounded-lg border border-dashed p-6 text-center sm:p-8">
        <div class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-muted">
            <Trophy class="size-6 text-muted-foreground" />
        </div>
        <p class="font-medium">Aún no hay equipos con estadísticas</p>
        <p class="mx-auto mt-1 max-w-sm text-xs text-muted-foreground">
            Crea los equipos de la temporada y asígnalos a los partidos. Cuando los partidos se completen con scores, verás la tabla aquí.
        </p>
    </div>

    <!-- Mobile card view -->
    <div v-else class="space-y-2 sm:hidden">
        <Link
            v-for="(row, i) in standings"
            :key="row.team_id"
            :href="`/clubs/${club.ulid}/teams/${row.team_ulid}`"
            class="block rounded-lg border border-border p-3 transition-colors hover:bg-accent"
        >
            <div class="flex items-center gap-3">
                <span class="w-5 shrink-0 text-center text-sm font-semibold text-muted-foreground">{{ i + 1 }}</span>
                <span
                    v-if="!row.logo_url"
                    class="flex size-8 shrink-0 items-center justify-center rounded-full border border-border text-xs font-black text-white"
                    :style="{ backgroundColor: row.color }"
                >{{ row.name.charAt(0).toUpperCase() }}</span>
                <img
                    v-else
                    :src="row.logo_url"
                    class="size-8 shrink-0 rounded-full border border-border object-cover"
                    alt=""
                >
                <p class="min-w-0 flex-1 truncate font-medium">{{ row.name }}</p>
                <div class="flex shrink-0 flex-col items-end gap-0.5">
                    <span class="text-lg font-black leading-none" :class="STAT_STYLES.Pts.label">{{ row.Pts }}</span>
                    <span class="text-[10px] font-semibold uppercase tracking-wider" :class="STAT_STYLES.Pts.label">Pts</span>
                </div>
            </div>

            <!-- Stats grid: matches the legend 1:1 — labels tinted, values neutral for easy reading -->
            <div class="mt-3 grid grid-cols-7 gap-1.5 text-center">
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider" :class="STAT_STYLES.PJ.label">PJ</div>
                    <div class="text-base font-bold tabular-nums">{{ row.PJ }}</div>
                </div>
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider" :class="STAT_STYLES.G.label">G</div>
                    <div class="text-base font-bold tabular-nums">{{ row.G }}</div>
                </div>
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider" :class="STAT_STYLES.E.label">E</div>
                    <div class="text-base font-bold tabular-nums">{{ row.E }}</div>
                </div>
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider" :class="STAT_STYLES.P.label">P</div>
                    <div class="text-base font-bold tabular-nums">{{ row.P }}</div>
                </div>
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider" :class="STAT_STYLES.GF.label">GF</div>
                    <div class="text-base font-bold tabular-nums">{{ row.GF }}</div>
                </div>
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider" :class="STAT_STYLES.GC.label">GC</div>
                    <div class="text-base font-bold tabular-nums">{{ row.GC }}</div>
                </div>
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider" :class="STAT_STYLES.DG.label">DG</div>
                    <div class="text-base font-bold tabular-nums">{{ row.DG > 0 ? '+' : '' }}{{ row.DG }}</div>
                </div>
            </div>

            <!-- Últimos 5 with letters inside circles -->
            <div class="mt-3 flex items-center gap-1.5">
                <span class="text-[9px] font-semibold uppercase tracking-wider text-muted-foreground">Últimos 5:</span>
                <div class="flex gap-1">
                    <template v-for="n in 5" :key="n">
                        <span
                            v-if="row.last5[n - 1]"
                            class="flex size-5 items-center justify-center rounded-full border text-[10px] font-black"
                            :class="last5Variant(row.last5[n - 1]).cls"
                            :aria-label="last5Variant(row.last5[n - 1]).label"
                            :title="last5Variant(row.last5[n - 1]).label"
                        >
                            {{ last5Variant(row.last5[n - 1]).letter }}
                        </span>
                        <span
                            v-else
                            class="inline-block size-5 rounded-full border border-dashed border-border"
                            aria-hidden="true"
                        />
                    </template>
                </div>
            </div>
        </Link>
    </div>

    <!-- Desktop table view -->
    <TooltipProvider v-if="standings.length > 0" :delay-duration="150">
        <div class="hidden overflow-x-auto rounded-lg border sm:block">
        <table class="w-full text-sm">
            <thead class="bg-muted/40">
                <tr class="text-xs uppercase tracking-wider text-muted-foreground">
                    <th class="px-3 py-2 text-left">#</th>
                    <th class="px-3 py-2 text-left">Club</th>
                    <th
                        v-for="h in headers"
                        :key="h.code"
                        class="px-2 py-2 text-center font-semibold"
                        :class="STAT_STYLES[h.code].label"
                    >
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <span class="cursor-help border-b border-dotted border-current/40">{{ h.code }}</span>
                            </TooltipTrigger>
                            <TooltipContent>{{ h.full }}</TooltipContent>
                        </Tooltip>
                    </th>
                    <th class="px-3 py-2 text-center">Últimos 5</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                <tr v-for="(row, i) in standings" :key="row.team_id" class="hover:bg-accent/50">
                    <td class="px-3 py-2.5 text-muted-foreground">{{ i + 1 }}</td>
                    <td class="px-3 py-2.5">
                        <Link
                            :href="`/clubs/${club.ulid}/teams/${row.team_ulid}`"
                            class="flex items-center gap-2.5 font-medium hover:underline"
                        >
                            <span
                                v-if="!row.logo_url"
                                class="flex size-6 shrink-0 items-center justify-center rounded-full border border-border text-[10px] font-black text-white"
                                :style="{ backgroundColor: row.color }"
                            >{{ row.name.charAt(0).toUpperCase() }}</span>
                            <img
                                v-else
                                :src="row.logo_url"
                                class="size-6 shrink-0 rounded-full border border-border object-cover"
                                alt=""
                            >
                            <span class="truncate">{{ row.name }}</span>
                        </Link>
                    </td>
                    <td class="px-2 py-2.5 text-center tabular-nums">{{ row.PJ }}</td>
                    <td class="px-2 py-2.5 text-center tabular-nums">{{ row.G }}</td>
                    <td class="px-2 py-2.5 text-center tabular-nums">{{ row.E }}</td>
                    <td class="px-2 py-2.5 text-center tabular-nums">{{ row.P }}</td>
                    <td class="px-2 py-2.5 text-center tabular-nums">{{ row.GF }}</td>
                    <td class="px-2 py-2.5 text-center tabular-nums">{{ row.GC }}</td>
                    <td class="px-2 py-2.5 text-center tabular-nums">{{ row.DG > 0 ? '+' : '' }}{{ row.DG }}</td>
                    <td class="px-2 py-2.5 text-center text-base font-black tabular-nums" :class="STAT_STYLES.Pts.label">{{ row.Pts }}</td>
                    <td class="px-3 py-2.5">
                        <div class="flex justify-center gap-1">
                            <template v-for="n in 5" :key="n">
                                <span
                                    v-if="row.last5[n - 1]"
                                    class="flex size-6 items-center justify-center rounded-full border text-[11px] font-black"
                                    :class="last5Variant(row.last5[n - 1]).cls"
                                    :aria-label="last5Variant(row.last5[n - 1]).label"
                                    :title="last5Variant(row.last5[n - 1]).label"
                                >
                                    {{ last5Variant(row.last5[n - 1]).letter }}
                                </span>
                                <span
                                    v-else
                                    class="inline-block size-6 rounded-full border border-dashed border-border"
                                    aria-hidden="true"
                                />
                            </template>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        </div>
    </TooltipProvider>

    <StandingsLegend v-if="standings.length > 0" />
</template>
