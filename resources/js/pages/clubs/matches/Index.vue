<script setup lang="ts">
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import { CalendarDays, Clock, MapPin, Plus, Users } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, FootballMatch } from '@/types';

type Paginated<T> = { data: T[]; next_page_url: string | null };

type Props = { club: Club; matches: Paginated<FootballMatch>; filter: string };
const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.id}` },
    { title: 'Partidos', href: `/clubs/${props.club.id}/matches` },
];

const tabs = [
    { key: 'all', label: 'Todos' },
    { key: 'upcoming', label: 'Proximos' },
    { key: 'completed', label: 'Finalizados' },
] as const;

function switchTab(tab: string) {
    const params: Record<string, string> = {};
    if (tab !== 'all') {
        params.filter = tab;
    }
    router.get(`/clubs/${props.club.id}/matches`, params, {
        preserveState: false,
    });
}

const statusLabel: Record<string, string> = {
    upcoming: 'Proximo',
    in_progress: 'En juego',
    completed: 'Finalizado',
    cancelled: 'Cancelado',
};

function formatDate(dateStr: string): string {
    const d = new Date(dateStr);
    return d.toLocaleDateString('es', { weekday: 'short', day: 'numeric', month: 'short' });
}

function formatTime(dateStr: string): string {
    const d = new Date(dateStr);
    return d.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Head :title="`${club.name} - Partidos`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-bold">Partidos</h1>
                <Link :href="`/clubs/${club.id}/matches/create`">
                    <Button><Plus class="mr-2 size-4" />Crear</Button>
                </Link>
            </div>

            <!-- Filter tabs -->
            <div class="mb-6 flex gap-1">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="filter === tab.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-accent'"
                    @click="switchTab(tab.key)"
                >
                    {{ tab.label }}
                </button>
            </div>

            <div v-if="matches.data.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                <p class="text-muted-foreground">No hay partidos.</p>
            </div>

            <InfiniteScroll v-else data="matches" #default="{ fetching }">
                <div class="space-y-3">
                    <Link
                        v-for="m in matches.data"
                        :key="m.id"
                        :href="`/clubs/${club.id}/matches/${m.id}`"
                        class="block rounded-lg border border-border p-4 transition-colors hover:bg-accent"
                    >
                        <div class="flex items-start justify-between">
                            <h3 class="font-semibold">{{ m.title }}</h3>
                            <Badge variant="outline" class="text-xs">{{ statusLabel[m.status] ?? m.status }}</Badge>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
                            <span class="flex items-center gap-1"><CalendarDays class="size-3.5" />{{ formatDate(m.scheduled_at) }}</span>
                            <span class="flex items-center gap-1"><Clock class="size-3.5" />{{ formatTime(m.scheduled_at) }}</span>
                            <span v-if="m.field" class="flex items-center gap-1"><MapPin class="size-3.5" />{{ m.field.name }}</span>
                            <span class="flex items-center gap-1"><Users class="size-3.5" />{{ m.attendances_count ?? 0 }}/{{ m.max_players }}</span>
                        </div>
                    </Link>
                </div>
                <div v-if="fetching" class="py-4 text-center text-sm text-muted-foreground">
                    Cargando mas partidos...
                </div>
            </InfiniteScroll>
        </div>
    </AppLayout>
</template>
