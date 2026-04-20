<script setup lang="ts">
import { InfiniteScroll, Link } from '@inertiajs/vue3';
import { Bell, BellOff, Shield, UserX } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import UserAvatar from '@/components/UserAvatar.vue';
import type { Club, Player } from '@/types';

type PaginatedPlayers = { data: Player[] };

const props = defineProps<{
    club: Club;
    players: PaginatedPlayers;
    goalkeepers: Player[];
    isAdmin: boolean;
}>();

const search = ref('');
const normalizedSearch = computed(() => search.value.toLowerCase().trim());

function matchesSearch(p: Player, q: string): boolean {
    return p.display_name.toLowerCase().includes(q)
        || p.position?.toLowerCase().includes(q)
        || String(p.jersey_number ?? '').includes(q);
}

const filteredPlayers = computed(() => {
    const q = normalizedSearch.value;
    if (!q) return props.players.data;
    return props.players.data.filter((p) => matchesSearch(p, q));
});

const filteredGoalkeepers = computed(() => {
    const q = normalizedSearch.value;
    if (!q) return props.goalkeepers;
    return props.goalkeepers.filter((p) => matchesSearch(p, q));
});

function getMedal(index: number): string {
    if (index === 0) return '\u{1F947}';
    if (index === 1) return '\u{1F948}';
    if (index === 2) return '\u{1F949}';
    return String(index + 1);
}
</script>

<template>
    <div>
        <div class="relative mb-4">
            <Input v-model="search" placeholder="Buscar jugador..." />
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
                    <UserAvatar
                        :src="player.photo_url"
                        :name="player.display_name"
                        class="size-9"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ player.display_name }}</p>
                        <div class="flex items-center gap-2">
                            <Badge v-if="player.position" variant="outline" class="text-[10px]">{{ player.position }}</Badge>
                            <span v-if="player.jersey_number" class="text-xs text-muted-foreground">#{{ player.jersey_number }}</span>
                            <span v-if="isAdmin && !player.user_id" class="text-amber-500" title="Sin usuario asociado">
                                <UserX class="size-3.5" />
                            </span>
                            <span v-if="isAdmin && player.user_id && player.has_push" class="text-green-500/60" title="Notificaciones activas">
                                <Bell class="size-3" />
                            </span>
                            <span v-if="isAdmin && player.user_id && !player.has_push" class="text-muted-foreground/40" title="Sin notificaciones">
                                <BellOff class="size-3" />
                            </span>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2 text-xs text-muted-foreground">
                        <div class="flex flex-col items-center">
                            <span class="font-semibold text-foreground">{{ player.matches_played ?? 0 }}</span>
                            <span class="text-[10px]">PJ</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <span class="font-semibold text-primary">{{ player.goals ?? 0 }}</span>
                            <span class="text-[10px]">Goles</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <span class="font-semibold text-foreground">{{ player.fouls ?? 0 }}</span>
                            <span class="text-[10px]">Faltas</span>
                        </div>
                    </div>
                </Link>
            </div>

            <template #loading>
                <div class="flex justify-center py-3">
                    <div class="size-5 animate-spin rounded-full border-2 border-muted-foreground border-t-transparent" />
                </div>
            </template>
        </InfiniteScroll>

        <template v-if="filteredGoalkeepers.length > 0">
            <div class="mb-4 mt-8 flex items-center justify-center gap-4 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                <span class="h-px flex-1 bg-border" />
                <span class="flex items-center gap-1.5">
                    <Shield class="size-3.5 text-blue-400" />
                    Porteros
                </span>
                <span class="h-px flex-1 bg-border" />
            </div>

            <div class="space-y-2">
                <Link
                    v-for="(gk, i) in filteredGoalkeepers"
                    :key="gk.id"
                    :href="`/clubs/${club.ulid}/players/${gk.ulid}`"
                    class="flex items-center gap-3 rounded-lg border border-blue-500/20 bg-blue-500/5 p-3 transition-colors hover:bg-blue-500/10"
                >
                    <span class="w-6 text-center text-sm">{{ getMedal(i) }}</span>
                    <UserAvatar :src="gk.photo_url" :name="gk.display_name" class="size-9" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ gk.display_name }}</p>
                        <div class="flex items-center gap-2">
                            <Badge variant="outline" class="border-blue-500/30 text-[10px] text-blue-400">GK</Badge>
                            <span v-if="gk.jersey_number" class="text-xs text-muted-foreground">#{{ gk.jersey_number }}</span>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2 text-xs text-muted-foreground">
                        <div class="flex flex-col items-center">
                            <span class="font-semibold text-foreground">{{ gk.matches_played ?? 0 }}</span>
                            <span class="text-[10px]">PJ</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <span class="font-semibold text-blue-400">{{ gk.saves ?? 0 }}</span>
                            <span class="text-[10px]">Atajadas</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <span class="font-semibold text-foreground">{{ gk.goals ?? 0 }}</span>
                            <span class="text-[10px]">Goles</span>
                        </div>
                    </div>
                </Link>
            </div>
        </template>
    </div>
</template>
