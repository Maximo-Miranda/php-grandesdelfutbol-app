<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarX } from 'lucide-vue-next';
import { computed } from 'vue';
import MatchTeamsScore from '@/components/match/MatchTeamsScore.vue';
import { formatDate } from '@/lib/utils';
import type { Club, MatchStatus, TeamSide } from '@/types';

type SeasonMatch = {
    ulid: string;
    scheduled_at: string;
    status: MatchStatus;
    is_friendly: boolean;
    team_a: TeamSide;
    team_b: TeamSide | null;
};

const props = defineProps<{
    club: Club;
    matches: SeasonMatch[] | undefined;
}>();

const groups = computed(() => {
    if (!props.matches) return [];
    const map = new Map<string, SeasonMatch[]>();
    for (const m of props.matches) {
        const day = formatDate(m.scheduled_at, { weekday: 'long', day: 'numeric', month: 'long' });
        const key = day.charAt(0).toUpperCase() + day.slice(1);
        if (!map.has(key)) map.set(key, []);
        map.get(key)!.push(m);
    }
    return Array.from(map, ([day, items]) => ({ day, items }));
});
</script>

<template>
    <div v-if="!matches" class="space-y-3">
        <div v-for="i in 3" :key="i" class="h-36 animate-pulse rounded-xl border border-border bg-muted/30" />
    </div>

    <div v-else-if="matches.length === 0" class="rounded-xl border border-dashed p-12 text-center text-muted-foreground">
        <CalendarX class="mx-auto mb-3 size-10 opacity-40" />
        <p class="text-sm">Aún no hay partidos en esta temporada.</p>
    </div>

    <div v-else class="space-y-8">
        <section v-for="group in groups" :key="group.day">
            <div class="mb-3 flex items-center gap-3">
                <h3 class="text-[11px] font-bold uppercase tracking-[0.15em] text-muted-foreground">{{ group.day }}</h3>
                <div class="h-px flex-1 bg-gradient-to-r from-border to-transparent" />
            </div>

            <div class="space-y-2.5">
                <Link
                    v-for="match in group.items"
                    :key="match.ulid"
                    :href="`/clubs/${club.ulid}/matches/${match.ulid}`"
                    class="group block overflow-hidden rounded-xl border border-border bg-card transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg"
                >
                    <div class="px-3 py-5 sm:px-6">
                        <MatchTeamsScore
                            :team-a="match.team_a"
                            :team-b="match.team_b"
                            :status="match.status"
                            :is-friendly="match.is_friendly"
                            :scheduled-at="match.scheduled_at"
                        />
                    </div>

                    <div
                        v-if="match.is_friendly"
                        class="flex items-center justify-center border-t border-border/40 bg-amber-500/5 py-1"
                    >
                        <span class="text-[9px] font-bold uppercase tracking-widest text-amber-600 dark:text-amber-500">
                            Partido amistoso · No suma puntos
                        </span>
                    </div>
                </Link>
            </div>
        </section>
    </div>
</template>
