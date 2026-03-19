<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import type { MatchAttendance } from '@/types';

const props = defineProps<{
    teamAPlayers: MatchAttendance[];
    teamBPlayers: MatchAttendance[];
    teamAName: string;
    teamBName: string;
    teamAColor: string | null;
    teamBColor: string | null;
    selectedPlayerId: number | null;
    disabledPlayerId?: number | null;
    recentPlayerIds?: number[];
    hideHeaders?: boolean;
}>();

const emit = defineEmits<{
    select: [playerId: number, playerName: string];
}>();

const searchQuery = ref('');

function filterPlayers(players: MatchAttendance[]) {
    const q = searchQuery.value.toLowerCase().trim();
    if (!q) return players;
    return players.filter(att =>
        att.player?.display_name?.toLowerCase().includes(q)
        || String(att.player?.jersey_number ?? '').includes(q),
    );
}

const filteredTeamA = computed(() => filterPlayers(props.teamAPlayers));
const filteredTeamB = computed(() => filterPlayers(props.teamBPlayers));

function selectFirstResult() {
    const first = filteredTeamA.value[0] ?? filteredTeamB.value[0];
    if (first) {
        emit('select', first.player_id, first.player?.display_name ?? '');
        searchQuery.value = '';
    }
}

const allPlayers = computed(() => [...props.teamAPlayers, ...props.teamBPlayers]);

const recentPlayers = computed(() => {
    if (!props.recentPlayerIds?.length) return [];
    return props.recentPlayerIds
        .map(id => allPlayers.value.find(a => a.player_id === id))
        .filter(Boolean) as MatchAttendance[];
});
</script>

<template>
    <!-- Search -->
    <div class="relative mb-2">
        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5">
            <Search class="size-3.5 text-muted-foreground" />
        </div>
        <input
            v-model="searchQuery"
            type="search"
            enterkeyhint="done"
            placeholder="Buscar por nombre o numero..."
            class="w-full rounded-lg border border-border bg-accent/50 py-2 pr-3 pl-8 text-xs outline-none placeholder:text-muted-foreground focus:ring-1 focus:ring-primary/40"
            @keydown.enter.prevent="selectFirstResult"
        />
    </div>

    <!-- Recent players -->
    <div v-if="recentPlayers.length && !searchQuery" class="mb-2 flex gap-1.5 overflow-x-auto pb-1">
        <button
            v-for="att in recentPlayers"
            :key="att.player_id"
            class="flex min-h-[44px] shrink-0 items-center gap-1.5 rounded-lg border px-2.5 py-1.5 transition-all active:scale-[0.97]"
            :class="selectedPlayerId === att.player_id
                ? 'border-primary bg-primary/15 ring-2 ring-primary/40'
                : 'border-border bg-accent/50 hover:bg-accent'"
            @click="emit('select', att.player_id, att.player?.display_name ?? '')"
        >
            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/20 text-[11px] font-bold">
                {{ att.player?.jersey_number ?? att.player?.display_name?.charAt(0) }}
            </span>
            <span class="text-xs font-medium">{{ att.player?.display_name?.split(' ')[0] }}</span>
        </button>
    </div>

    <!-- Teams side by side -->
    <div class="grid grid-cols-2 gap-2">
        <!-- Team A -->
        <div>
            <p v-if="!hideHeaders" class="mb-1 text-center text-[10px] font-bold tracking-wide text-zinc-400 uppercase">{{ teamAName }}</p>
            <div class="space-y-1">
                <button
                    v-for="att in filteredTeamA"
                    :key="att.id"
                    :disabled="disabledPlayerId === att.player_id"
                    class="flex min-h-[44px] w-full items-center gap-2 rounded-lg border px-2 py-2 text-left transition-all active:scale-[0.97] disabled:opacity-40 disabled:pointer-events-none"
                    :class="selectedPlayerId === att.player_id
                        ? 'border-primary bg-primary/15 ring-2 ring-primary/40 shadow-sm shadow-primary/20'
                        : 'border-border bg-accent/50 hover:bg-accent'"
                    @click="emit('select', att.player_id, att.player?.display_name ?? '')"
                >
                    <span
                        class="flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white"
                        :style="{ backgroundColor: selectedPlayerId === att.player_id ? undefined : (teamAColor ?? '#6b7280') }"
                        :class="selectedPlayerId === att.player_id ? 'bg-primary text-primary-foreground' : ''"
                    >{{ att.player?.jersey_number ?? att.player?.display_name?.charAt(0) }}</span>
                    <span class="min-w-0 truncate text-xs font-medium">{{ att.player?.display_name }}</span>
                </button>
            </div>
        </div>

        <!-- Team B -->
        <div>
            <p v-if="!hideHeaders" class="mb-1 text-center text-[10px] font-bold tracking-wide text-zinc-400 uppercase">{{ teamBName }}</p>
            <div class="space-y-1">
                <button
                    v-for="att in filteredTeamB"
                    :key="att.id"
                    :disabled="disabledPlayerId === att.player_id"
                    class="flex min-h-[44px] w-full items-center gap-2 rounded-lg border px-2 py-2 text-left transition-all active:scale-[0.97] disabled:opacity-40 disabled:pointer-events-none"
                    :class="selectedPlayerId === att.player_id
                        ? 'border-primary bg-primary/15 ring-2 ring-primary/40 shadow-sm shadow-primary/20'
                        : 'border-border bg-accent/50 hover:bg-accent'"
                    @click="emit('select', att.player_id, att.player?.display_name ?? '')"
                >
                    <span
                        class="flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white"
                        :style="{ backgroundColor: selectedPlayerId === att.player_id ? undefined : (teamBColor ?? '#6b7280') }"
                        :class="selectedPlayerId === att.player_id ? 'bg-primary text-primary-foreground' : ''"
                    >{{ att.player?.jersey_number ?? att.player?.display_name?.charAt(0) }}</span>
                    <span class="min-w-0 truncate text-xs font-medium">{{ att.player?.display_name }}</span>
                </button>
            </div>
        </div>
    </div>
</template>
