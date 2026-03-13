<script setup lang="ts">
import { Head, InfiniteScroll, Link } from '@inertiajs/vue3';
import { Plus, Search, UserPlus, UserX } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useClubPermissions } from '@/composables/useClubPermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, Player } from '@/types';

type PaginatedPlayers = { data: Player[] };

type Props = { club: Club; players: PaginatedPlayers };
const props = defineProps<Props>();
const { isAdmin } = useClubPermissions();

const search = ref('');
const filteredPlayers = computed(() => {
    const q = search.value.toLowerCase().trim();
    if (!q) return props.players.data;
    return props.players.data.filter((p) =>
        p.display_name.toLowerCase().includes(q)
        || p.position?.toLowerCase().includes(q)
        || String(p.jersey_number ?? '').includes(q),
    );
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
    { title: 'Jugadores', href: `/clubs/${props.club.ulid}/players` },
];

function getMedal(index: number): string {
    if (index === 0) return '\u{1F947}';
    if (index === 1) return '\u{1F948}';
    if (index === 2) return '\u{1F949}';
    return String(index + 1);
}

function getGoalsPerMatch(player: Player): string {
    const mp = player.matches_played ?? 0;
    if (mp === 0) return '-';
    return (((player.goals ?? 0) + (player.assists ?? 0)) / mp).toFixed(1);
}
</script>

<template>
    <Head :title="`${club.name} - Jugadores`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-bold">Jugadores</h1>
                <div v-if="isAdmin" class="flex gap-2">
                    <Link :href="`/clubs/${club.ulid}/players/create`">
                        <Button><Plus class="mr-2 size-4" />Crear</Button>
                    </Link>
                    <Link :href="`/clubs/${club.ulid}/members`">
                        <Button variant="outline"><UserPlus class="mr-2 size-4" />Invitar</Button>
                    </Link>
                </div>
            </div>

            <div v-if="!players?.data?.length" class="rounded-lg border border-dashed p-8 text-center">
                <p class="text-muted-foreground">No hay jugadores.</p>
            </div>

            <template v-else>
                <div class="relative mb-4">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="search"
                        placeholder="Buscar jugador..."
                        class="pl-9"
                    />
                </div>

                <div class="mb-4 flex items-center justify-center gap-4 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    <span class="h-px flex-1 bg-border" />
                    <span>Tabla de posiciones</span>
                    <span class="h-px flex-1 bg-border" />
                </div>

                <div v-if="filteredPlayers.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                    <p class="text-muted-foreground">No se encontraron jugadores.</p>
                </div>

                <InfiniteScroll data="players" only-next>
                    <div class="space-y-2">
                        <Link
                            v-for="(player, i) in filteredPlayers"
                            :key="player.id"
                            :href="`/clubs/${club.ulid}/players/${player.ulid}`"
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
                                    <span v-if="isAdmin && !player.user_id" class="text-amber-500" title="Sin usuario asociado">
                                        <UserX class="size-3.5" />
                                    </span>
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

                    <template #loading>
                        <div class="flex justify-center py-3">
                            <div class="size-5 animate-spin rounded-full border-2 border-muted-foreground border-t-transparent" />
                        </div>
                    </template>
                </InfiniteScroll>
            </template>
        </div>
    </AppLayout>
</template>
