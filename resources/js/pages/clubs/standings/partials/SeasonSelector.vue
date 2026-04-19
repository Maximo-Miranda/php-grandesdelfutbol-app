<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Season = {
    ulid: string;
    name: string;
    matches_count: number;
    status: string;
    is_active: boolean;
};

const props = defineProps<{
    seasons: Season[];
    selected: string;
    tab: string;
}>();

const model = computed({
    get: () => props.selected,
    set: (value: string) => {
        router.get(window.location.pathname, { season: value, tab: props.tab }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    },
});
</script>

<template>
    <Select v-model="model">
        <SelectTrigger class="w-56">
            <SelectValue placeholder="Selecciona temporada" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem v-for="s in seasons" :key="s.ulid" :value="s.ulid">
                <div class="flex items-center gap-2">
                    <span>{{ s.name }}</span>
                    <span
                        v-if="s.is_active"
                        class="rounded-full border border-emerald-500/40 bg-emerald-500/15 px-2 py-0.5 text-[10px] font-semibold text-emerald-500"
                    >Activa</span>
                </div>
            </SelectItem>
        </SelectContent>
    </Select>
</template>
