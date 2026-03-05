<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus, UserPlus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, Player } from '@/types';

type Props = { club: Club; players: Player[] };
const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.id}` },
    { title: 'Jugadores', href: `/clubs/${props.club.id}/players` },
];

const search = ref('');

const sortedPlayers = computed(() => {
    const q = search.value.toLowerCase();
    const filtered = props.players.filter(p =>
        p.name.toLowerCase().includes(q) || p.display_name.toLowerCase().includes(q),
    );
    return [...filtered].sort((a, b) => {
        const scoreA = (a.goals ?? 0) + (a.assists ?? 0);
        const scoreB = (b.goals ?? 0) + (b.assists ?? 0);
        return scoreB - scoreA;
    });
});

function getMedal(index: number): string {
    if (index === 0) return '\u{1F947}';
    if (index === 1) return '\u{1F948}';
    if (index === 2) return '\u{1F949}';
    return String(index + 1);
}

function getGoalsPerMatch(player: Player): string {
    const mp = player.matches_played ?? 0;
    if (mp === 0) return '-';
    return ((player.goals ?? 0) + (player.assists ?? 0) / mp).toFixed(1);
}
</script>

<template>
    <Head :title="`${club.name} - Jugadores`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-bold">Jugadores</h1>
                <div class="flex gap-2">
                    <Link :href="`/clubs/${club.id}/players/create`">
                        <Button><Plus class="mr-2 size-4" />Crear</Button>
                    </Link>
                    <Link :href="`/clubs/${club.id}/invite`">
                        <Button variant="outline"><UserPlus class="mr-2 size-4" />Invitar</Button>
                    </Link>
                </div>
            </div>

            <div class="mb-6">
                <Input v-model="search" placeholder="Buscar jugador..." class="max-w-xs" />
            </div>

            <div v-if="sortedPlayers.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                <p class="text-muted-foreground">No hay jugadores.</p>
            </div>

            <template v-else>
                <div class="mb-4 flex items-center justify-center gap-4 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    <span class="h-px flex-1 bg-border" />
                    <span>Tabla de posiciones</span>
                    <span class="h-px flex-1 bg-border" />
                </div>

                <div class="space-y-2">
                    <Link
                        v-for="(player, i) in sortedPlayers"
                        :key="player.id"
                        :href="`/clubs/${club.id}/players/${player.id}`"
                        class="flex items-center gap-3 rounded-lg border border-border p-3 transition-colors hover:bg-accent"
                    >
                        <span class="w-6 text-center text-sm">{{ getMedal(i) }}</span>
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-bold">
                            {{ player.display_name.charAt(0) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ player.display_name }}</p>
                            <div class="flex items-center gap-2">
                                <Badge v-if="player.position" variant="outline" class="text-[10px]">{{ player.position }}</Badge>
                                <span v-if="player.jersey_number" class="text-xs text-muted-foreground">#{{ player.jersey_number }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 text-sm text-muted-foreground">
                            <span>{{ player.matches_played ?? 0 }} PJ</span>
                            <span class="text-primary">{{ player.goals ?? 0 }}</span>
                            <span>{{ player.assists ?? 0 }}</span>
                            <span>{{ getGoalsPerMatch(player) }} G+A</span>
                        </div>
                    </Link>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
