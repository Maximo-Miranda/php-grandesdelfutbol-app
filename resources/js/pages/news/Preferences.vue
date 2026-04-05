<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Settings2 } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, UserNewsPreference } from '@/types';

defineProps<{
    preference: UserNewsPreference | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Noticias', href: '/news' },
    { title: 'Preferencias', href: '/news/preferences' },
];
</script>

<template>
    <Head title="Preferencias de Noticias" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl px-4 py-6">
            <div class="mb-6 flex items-center gap-3">
                <Settings2 class="size-6 text-primary" />
                <h1 class="text-lg font-bold">Preferencias de Noticias</h1>
            </div>

            <div class="rounded-lg border border-border p-6 text-center">
                <p class="text-sm text-muted-foreground">
                    Las preferencias personalizadas estarán disponibles próximamente.
                </p>
                <p class="mt-2 text-xs text-muted-foreground">
                    Podrás seleccionar tus equipos, ligas y temas favoritos para personalizar tu feed.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
