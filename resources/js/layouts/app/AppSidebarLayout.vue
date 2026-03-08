<script setup lang="ts">
import { router, usePoll } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import PwaInstallPrompt from '@/components/PwaInstallPrompt.vue';
import ToastContainer from '@/components/ToastContainer.vue';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

// Poll server every 5 min — Inertia detects version change (409) and forces full reload
usePoll(5 * 60 * 1000);

const showBack = computed(() => props.breadcrumbs.length >= 2);
const backFallback = computed(() => {
    if (props.breadcrumbs.length >= 2) {
        return props.breadcrumbs[props.breadcrumbs.length - 2].href;
    }
    return undefined;
});

function goBack() {
    if (window.history.length > 1) {
        window.history.back();
    } else if (backFallback.value) {
        router.visit(backFallback.value);
    }
}
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <button
                v-if="showBack"
                type="button"
                class="mx-auto hidden w-full max-w-2xl items-center gap-1.5 px-4 pt-6 text-sm text-muted-foreground transition-colors hover:text-foreground lg:inline-flex"
                @click="goBack"
            >
                <ArrowLeft class="size-4" />
                Volver
            </button>
            <slot />
        </AppContent>
    </AppShell>
    <ToastContainer />
    <PwaInstallPrompt />
</template>
