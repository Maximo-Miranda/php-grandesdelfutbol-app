<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Globe, Trophy } from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed } from 'vue';

const props = defineProps<{
    currentCategory: string | null;
    hasPreferences?: boolean;
}>();

const categories: { key: string; label: string; icon?: Component }[] = [
    { key: 'all', label: 'Todas' },
    { key: 'champions_league', label: 'Champions', icon: Trophy },
    { key: 'la_liga', label: 'La Liga' },
    { key: 'premier_league', label: 'Premier' },
    { key: 'copa_libertadores', label: 'Libertadores' },
    { key: 'liga_betplay', label: 'BetPlay' },
    { key: 'liga_mx', label: 'Liga MX' },
    { key: 'mundial', label: 'Mundial', icon: Globe },
    { key: 'transfers', label: 'Fichajes' },
];

const activeKey = computed(() =>
    props.currentCategory ?? (props.hasPreferences ? '' : 'all'),
);

function selectCategory(key: string | null) {
    router.visit('/news', {
        data: key ? { category: key } : {},
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <button
            v-for="cat in categories"
            :key="cat.key"
            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium transition-colors"
            :class="
                activeKey === cat.key
                    ? 'bg-primary text-primary-foreground shadow-sm'
                    : 'bg-muted text-muted-foreground hover:bg-accent hover:text-accent-foreground'
            "
            @click="selectCategory(cat.key)"
        >
            <component :is="cat.icon" v-if="cat.icon" class="size-3" />
            {{ cat.label }}
        </button>
    </div>
</template>
