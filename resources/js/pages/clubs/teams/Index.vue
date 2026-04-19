<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Copy, Plus } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club } from '@/types';

type Team = {
    id: number;
    ulid: string;
    name: string;
    color: string;
    logo_url: string | null;
    cover_url: string | null;
    players_count: number;
    season: { ulid: string; name: string; is_active: boolean };
    coach: { ulid: string; name: string; photo_url: string | null } | null;
    captain: { ulid: string; name: string; photo_url: string | null } | null;
};

type SeasonGroup = {
    season: { ulid: string; name: string; is_active: boolean };
    teams: Team[];
};

const props = defineProps<{
    club: Club;
    isAdmin: boolean;
    teamsBySeason: SeasonGroup[];
    hasPreviousSeasonWithTeams: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubes', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
    { title: 'Posiciones', href: `/clubs/${props.club.ulid}/standings` },
    { title: 'Equipos', href: `/clubs/${props.club.ulid}/teams` },
];

function copyFromPrevious(): void {
    if (!confirm('¿Copiar equipos de la temporada anterior a la actual?')) return;
    router.post(`/clubs/${props.club.ulid}/teams/copy-from-previous`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`${club.name} - Equipos`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h1 class="text-2xl font-bold">Equipos</h1>
                <div v-if="isAdmin" class="flex gap-2">
                    <Button
                        v-if="hasPreviousSeasonWithTeams"
                        variant="outline"
                        @click="copyFromPrevious"
                    >
                        <Copy class="mr-2 size-4" />
                        Copiar de anterior
                    </Button>
                    <Link :href="`/clubs/${club.ulid}/teams/create`">
                        <Button>
                            <Plus class="mr-2 size-4" />
                            Crear equipo
                        </Button>
                    </Link>
                </div>
            </div>

            <div v-if="teamsBySeason.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                <p class="text-muted-foreground">Aún no hay equipos creados.</p>
                <p v-if="isAdmin" class="mt-2 text-xs text-muted-foreground">Crea el primer equipo para comenzar a ver la tabla de posiciones.</p>
            </div>

            <div v-else class="space-y-8">
                <section v-for="group in teamsBySeason" :key="group.season.ulid">
                    <div class="mb-3 flex items-center gap-3">
                        <h2 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">{{ group.season.name }}</h2>
                        <span
                            v-if="group.season.is_active"
                            class="rounded-full border border-emerald-500/40 bg-emerald-500/15 px-2 py-0.5 text-[10px] font-semibold text-emerald-500"
                        >Activa</span>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <Link
                            v-for="team in group.teams"
                            :key="team.ulid"
                            :href="`/clubs/${club.ulid}/teams/${team.ulid}`"
                            class="group relative overflow-hidden rounded-lg border border-border transition hover:border-primary/60"
                        >
                            <div
                                class="h-20 w-full"
                                :style="{ background: team.cover_url ? `url(${team.cover_url}) center/cover no-repeat` : `linear-gradient(135deg, ${team.color}, ${team.color}99)` }"
                            />
                            <div class="flex items-center gap-3 p-3">
                                <img
                                    v-if="team.logo_url"
                                    :src="team.logo_url"
                                    class="-mt-8 size-12 rounded-full border-2 border-background object-cover shadow"
                                    alt=""
                                >
                                <span
                                    v-else
                                    class="-mt-8 flex size-12 items-center justify-center rounded-full border-2 border-background text-lg font-bold text-white shadow"
                                    :style="{ backgroundColor: team.color }"
                                >{{ team.name.charAt(0).toUpperCase() }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium">{{ team.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ team.players_count }} jugadores</p>
                                </div>
                            </div>
                        </Link>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
