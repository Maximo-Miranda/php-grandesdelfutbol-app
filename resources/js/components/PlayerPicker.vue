<script setup lang="ts">
import { onClickOutside } from '@vueuse/core';
import { Check, ChevronDown, Search, X } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import { stripDiacritics } from '@/lib/utils';

type PlayerOption = {
    id: number;
    name: string;
    jersey_number?: number | null;
    position?: string | null;
};

const props = withDefaults(defineProps<{
    players: PlayerOption[];
    placeholder?: string;
    emptyLabel?: string;
    disabled?: boolean;
    showJersey?: boolean;
    showPosition?: boolean;
}>(), {
    placeholder: '— Sin asignar —',
    emptyLabel: 'No hay coincidencias',
    disabled: false,
    showJersey: true,
    showPosition: false,
});

const model = defineModel<number | null>({ default: null });

const open = ref(false);
const query = ref('');
const highlight = ref(0);
const root = ref<HTMLElement | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);
const listRef = ref<HTMLElement | null>(null);

const selected = computed<PlayerOption | null>(
    () => props.players.find(p => p.id === model.value) ?? null,
);

const filtered = computed<PlayerOption[]>(() => {
    const q = stripDiacritics(query.value.trim());
    if (!q) return props.players;
    return props.players.filter(p => {
        const haystack = stripDiacritics(`${p.name} ${p.jersey_number ?? ''} ${p.position ?? ''}`);
        return haystack.includes(q);
    });
});

function formatPlayer(p: PlayerOption): string {
    const parts = [p.name];
    if (props.showJersey && p.jersey_number != null) parts.push(`#${p.jersey_number}`);
    if (props.showPosition && p.position) parts.push(`(${p.position})`);
    return parts.join(' ');
}

function toggle(): void {
    if (props.disabled) return;
    open.value = !open.value;
}

function close(): void {
    open.value = false;
    query.value = '';
    highlight.value = 0;
}

function select(player: PlayerOption | null): void {
    model.value = player?.id ?? null;
    close();
}

function move(delta: number): void {
    const total = filtered.value.length;
    if (total === 0) return;
    highlight.value = (highlight.value + delta + total) % total;
    nextTick(() => {
        const el = listRef.value?.querySelector<HTMLElement>(`[data-index="${highlight.value}"]`);
        el?.scrollIntoView({ block: 'nearest' });
    });
}

function commit(): void {
    const item = filtered.value[highlight.value];
    if (item) select(item);
}

watch(open, async (value) => {
    if (value) {
        highlight.value = 0;
        await nextTick();
        searchInput.value?.focus();
    }
});

watch(query, () => { highlight.value = 0; });

onClickOutside(root, () => {
    if (open.value) close();
});
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            :disabled="disabled"
            :aria-expanded="open"
            class="flex w-full items-center justify-between gap-2 rounded-md border border-input bg-background px-3 py-2 text-left text-sm transition hover:border-muted-foreground/50 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
            @click="toggle"
        >
            <span :class="selected ? 'truncate text-foreground' : 'text-muted-foreground'">
                {{ selected ? formatPlayer(selected) : placeholder }}
            </span>
            <div class="flex shrink-0 items-center gap-1">
                <button
                    v-if="selected && !disabled"
                    type="button"
                    class="rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                    aria-label="Limpiar selección"
                    @click.stop="select(null)"
                >
                    <X class="size-3.5" />
                </button>
                <ChevronDown class="size-4 text-muted-foreground" :class="{ 'rotate-180': open }" />
            </div>
        </button>

        <div
            v-if="open"
            class="absolute z-50 mt-1 w-full overflow-hidden rounded-md border border-border bg-popover shadow-lg"
        >
            <div class="flex items-center gap-2 border-b border-border px-3 py-2">
                <Search class="size-3.5 shrink-0 text-muted-foreground" />
                <input
                    ref="searchInput"
                    v-model="query"
                    type="text"
                    placeholder="Buscar jugador..."
                    class="w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                    @keydown.down.prevent="move(1)"
                    @keydown.up.prevent="move(-1)"
                    @keydown.enter.prevent="commit"
                    @keydown.esc.prevent="close"
                />
            </div>

            <div ref="listRef" class="max-h-64 overflow-y-auto">
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-2 border-b border-border/50 px-3 py-2 text-left text-sm text-muted-foreground transition hover:bg-accent"
                    @click="select(null)"
                >
                    <span>{{ placeholder }}</span>
                    <Check v-if="!selected" class="size-3.5" />
                </button>

                <div v-if="filtered.length === 0" class="px-3 py-6 text-center text-xs text-muted-foreground">
                    {{ emptyLabel }}
                </div>

                <button
                    v-for="(p, i) in filtered"
                    :key="p.id"
                    :data-index="i"
                    type="button"
                    class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm transition"
                    :class="[
                        i === highlight ? 'bg-accent text-foreground' : 'hover:bg-accent/50',
                        p.id === model ? 'font-semibold' : '',
                    ]"
                    @mouseenter="highlight = i"
                    @click="select(p)"
                >
                    <span class="flex min-w-0 items-center gap-1.5">
                        <span class="truncate">{{ p.name }}</span>
                        <span v-if="showJersey && p.jersey_number != null" class="shrink-0 text-xs text-muted-foreground tabular-nums">#{{ p.jersey_number }}</span>
                        <span v-if="showPosition && p.position" class="shrink-0 rounded bg-muted px-1 text-[10px] font-bold uppercase tracking-wider text-muted-foreground">{{ p.position }}</span>
                    </span>
                    <Check v-if="p.id === model" class="size-3.5 shrink-0 text-primary" />
                </button>
            </div>

            <div class="border-t border-border/50 bg-muted/30 px-3 py-1.5 text-[10px] text-muted-foreground">
                {{ filtered.length }} de {{ players.length }} jugadores
            </div>
        </div>
    </div>
</template>
