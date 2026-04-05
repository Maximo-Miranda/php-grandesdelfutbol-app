<script setup lang="ts">
import { router } from '@inertiajs/vue3';

defineProps<{
    currentCategory: string | null;
}>();

const categories = [
    { key: null, label: 'Todas' },
    { key: 'champions_league', label: 'Champions' },
    { key: 'la_liga', label: 'La Liga' },
    { key: 'premier_league', label: 'Premier' },
    { key: 'copa_libertadores', label: 'Libertadores' },
    { key: 'liga_betplay', label: 'BetPlay' },
    { key: 'liga_mx', label: 'Liga MX' },
    { key: 'transfers', label: 'Fichajes' },
    { key: 'mundial', label: 'Mundial' },
];

function selectCategory(key: string | null) {
    router.visit('/news', {
        data: key ? { category: key } : {},
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="no-scrollbar flex gap-2 overflow-x-auto pb-1">
        <button
            v-for="cat in categories"
            :key="cat.key ?? 'all'"
            class="shrink-0 rounded-full px-3 py-1.5 text-xs font-medium transition-colors"
            :class="
                currentCategory === cat.key
                    ? 'bg-primary text-primary-foreground'
                    : 'bg-muted text-muted-foreground hover:bg-accent hover:text-accent-foreground'
            "
            @click="selectCategory(cat.key)"
        >
            {{ cat.label }}
        </button>
    </div>
</template>
