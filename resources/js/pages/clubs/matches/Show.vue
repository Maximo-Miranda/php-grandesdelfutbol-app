<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarDays, Clock, MapPin, Users } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, FootballMatch, Player } from '@/types';

type Props = { club: Club; match: FootballMatch; players: Player[] };
const props = defineProps<Props>();

const base = `/clubs/${props.club.id}/matches/${props.match.id}`;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.id}` },
    { title: 'Partidos', href: `/clubs/${props.club.id}/matches` },
    { title: props.match.title, href: base },
];

function registerPlayer(playerId: number, status: string) {
    router.post(`${base}/attendance`, { player_id: playerId, status });
}

function startMatch() { router.post(`${base}/start`); }
function cancelMatch() { router.post(`${base}/cancel`); }
function completeMatch() { router.post(`${base}/complete`); }
function finalizeStats() { router.post(`${base}/finalize-stats`); }

const statusLabel: Record<string, string> = {
    upcoming: 'Proximo',
    in_progress: 'En juego',
    completed: 'Finalizado',
    cancelled: 'Cancelado',
};

const statusVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    upcoming: 'outline',
    in_progress: 'destructive',
    completed: 'default',
    cancelled: 'secondary',
};
</script>

<template>
    <Head :title="match.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ match.title }}</h1>
                    <Badge :variant="statusVariant[match.status] ?? 'outline'" class="mt-1">
                        {{ statusLabel[match.status] ?? match.status }}
                    </Badge>
                </div>
                <div class="flex items-center gap-2">
                    <Link v-if="match.status === 'upcoming' || match.status === 'in_progress'" :href="`${base}/edit`">
                        <Button variant="outline" size="sm">Editar</Button>
                    </Link>
                </div>
            </div>

            <!-- Lifecycle actions -->
            <div class="mb-6 flex flex-wrap gap-2">
                <template v-if="match.status === 'upcoming'">
                    <Button @click="startMatch">Iniciar partido</Button>
                    <Button variant="destructive" @click="cancelMatch">Cancelar</Button>
                </template>
                <template v-else-if="match.status === 'in_progress'">
                    <Link :href="`${base}/live`">
                        <Button>Panel en vivo</Button>
                    </Link>
                    <Button variant="outline" @click="completeMatch">Terminar</Button>
                    <Button variant="destructive" @click="cancelMatch">Cancelar</Button>
                </template>
                <template v-else-if="match.status === 'completed'">
                    <Link :href="`${base}/summary`">
                        <Button variant="outline">Ver resumen</Button>
                    </Link>
                    <Button v-if="!match.stats_finalized_at" @click="finalizeStats">Finalizar estadisticas</Button>
                    <Badge v-else variant="secondary">Estadisticas finalizadas</Badge>
                </template>
            </div>

            <!-- Details -->
            <div class="mb-6 rounded-lg border border-border p-4">
                <h3 class="mb-3 font-semibold">Detalles</h3>
                <div class="space-y-2 text-sm">
                    <p class="flex items-center gap-2"><CalendarDays class="size-4 text-muted-foreground" />{{ new Date(match.scheduled_at).toLocaleString('es') }}</p>
                    <p class="flex items-center gap-2"><Clock class="size-4 text-muted-foreground" />{{ match.duration_minutes }} min</p>
                    <p class="flex items-center gap-2"><Users class="size-4 text-muted-foreground" />{{ match.max_players }} jugadores, {{ match.max_substitutes }} suplentes</p>
                    <p v-if="match.field" class="flex items-center gap-2"><MapPin class="size-4 text-muted-foreground" />{{ match.field.name }}</p>
                    <p v-if="match.notes" class="text-muted-foreground">{{ match.notes }}</p>
                    <p v-if="match.share_token" class="text-muted-foreground">
                        Link publico:
                        <Link :href="`/match/${match.share_token}`" class="text-primary underline">/match/{{ match.share_token }}</Link>
                    </p>
                </div>
            </div>

            <!-- Attendance -->
            <div class="mb-6 rounded-lg border border-border p-4">
                <h3 class="mb-3 font-semibold">
                    Asistencia ({{ match.attendances?.filter(a => a.status === 'confirmed').length ?? 0 }}/{{ match.max_players }})
                </h3>
                <div v-if="match.attendances?.length" class="space-y-2">
                    <div v-for="att in match.attendances" :key="att.id" class="flex items-center justify-between">
                        <span class="text-sm">{{ att.player?.name }}</span>
                        <div class="flex gap-1">
                            <Badge :variant="att.status === 'confirmed' ? 'default' : 'secondary'" class="text-xs">{{ att.status }}</Badge>
                            <Badge v-if="att.role !== 'pending'" variant="outline" class="text-xs">{{ att.role }}</Badge>
                            <Badge v-if="att.team" variant="outline" class="text-xs">Eq. {{ att.team.toUpperCase() }}</Badge>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">Sin registros.</p>
            </div>

            <!-- Events -->
            <div v-if="match.events?.length" class="mb-6 rounded-lg border border-border p-4">
                <h3 class="mb-3 font-semibold">Eventos ({{ match.events.length }})</h3>
                <div class="space-y-1">
                    <div v-for="event in match.events" :key="event.id" class="flex items-center justify-between text-sm">
                        <span>{{ event.player?.name }}</span>
                        <div class="flex items-center gap-1">
                            <Badge variant="outline" class="text-xs">{{ event.event_type.replace(/_/g, ' ') }}</Badge>
                            <span class="text-muted-foreground">{{ event.minute }}'</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Register players -->
            <div v-if="match.status === 'upcoming' && players.length" class="rounded-lg border border-border p-4">
                <h3 class="mb-3 font-semibold">Registrar jugadores</h3>
                <div class="space-y-2">
                    <div v-for="player in players" :key="player.id" class="flex items-center justify-between rounded-md border border-border p-2">
                        <span class="text-sm">{{ player.name }}</span>
                        <div class="flex gap-2">
                            <Button size="sm" @click="registerPlayer(player.id, 'confirmed')">Confirmar</Button>
                            <Button size="sm" variant="outline" @click="registerPlayer(player.id, 'declined')">Rechazar</Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
