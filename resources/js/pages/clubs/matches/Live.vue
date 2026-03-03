<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { ArrowLeft, CircleDot, RectangleVertical, RefreshCw, Shuffle, Trash2, UserPlus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, FootballMatch, MatchEvent, Player } from '@/types';

type Props = { club: Club; match: FootballMatch; players: Player[] };
const props = defineProps<Props>();

const base = `/clubs/${props.club.id}/matches`;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.id}` },
    { title: 'Partidos', href: base },
    { title: props.match.title, href: `${base}/${props.match.id}` },
    { title: 'Live', href: `${base}/${props.match.id}/live` },
];

const quickEvents = [
    { value: 'goal', label: 'Gol', icon: CircleDot },
    { value: 'assist', label: 'Asist.', icon: CircleDot },
    { value: 'yellow_card', label: 'Amarilla', icon: RectangleVertical },
    { value: 'red_card', label: 'Roja', icon: RectangleVertical },
];

const allEventTypes = [
    { value: 'goal', label: 'Gol' },
    { value: 'assist', label: 'Asistencia' },
    { value: 'yellow_card', label: 'Tarjeta amarilla' },
    { value: 'red_card', label: 'Tarjeta roja' },
    { value: 'penalty_scored', label: 'Penal anotado' },
    { value: 'penalty_missed', label: 'Penal fallado' },
    { value: 'free_kick', label: 'Tiro libre' },
    { value: 'save', label: 'Atajada' },
    { value: 'own_goal', label: 'Autogol' },
];

const showMoreEvents = ref(false);
const selectedPlayerId = ref('');
const selectedEventType = ref('');
const minute = ref(0);

usePoll(10000);

const teamAPlayers = computed(() =>
    props.match.attendances?.filter(a => a.team === 'a').map(a => ({ ...a, player: a.player })) ?? [],
);

const teamBPlayers = computed(() =>
    props.match.attendances?.filter(a => a.team === 'b').map(a => ({ ...a, player: a.player })) ?? [],
);

const teamAGoals = computed(() =>
    (props.match.events ?? []).filter(
        (e: MatchEvent) => e.event_type === 'goal' && props.match.attendances?.find(a => a.player_id === e.player_id)?.team === 'a',
    ).length,
);

const teamBGoals = computed(() =>
    (props.match.events ?? []).filter(
        (e: MatchEvent) => e.event_type === 'goal' && props.match.attendances?.find(a => a.player_id === e.player_id)?.team === 'b',
    ).length,
);

const sortedEvents = computed(() =>
    [...(props.match.events ?? [])].sort((a, b) => b.minute - a.minute),
);

const statusLabel: Record<string, string> = {
    upcoming: 'PROXIMO',
    in_progress: 'EN JUEGO',
    completed: 'FINALIZADO',
    cancelled: 'CANCELADO',
};

const eventLabel: Record<string, string> = {
    goal: 'Gol',
    assist: 'Asistencia',
    yellow_card: 'Tarjeta amarilla',
    red_card: 'Tarjeta roja',
    penalty_scored: 'Penal anotado',
    penalty_missed: 'Penal fallado',
    own_goal: 'Autogol',
    save: 'Atajada',
    free_kick: 'Tiro libre',
};

function recordQuickEvent(eventType: string) {
    if (!selectedPlayerId.value) return;
    router.post(`${base}/${props.match.id}/events`, {
        player_id: Number(selectedPlayerId.value),
        event_type: eventType,
        minute: minute.value,
    }, { preserveScroll: true, onSuccess: () => { selectedPlayerId.value = ''; } });
}

function recordEvent() {
    if (!selectedPlayerId.value || !selectedEventType.value) return;
    router.post(`${base}/${props.match.id}/events`, {
        player_id: Number(selectedPlayerId.value),
        event_type: selectedEventType.value,
        minute: minute.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { selectedPlayerId.value = ''; selectedEventType.value = ''; },
    });
}

function removeEvent(eventId: number) {
    router.delete(`${base}/${props.match.id}/events/${eventId}`, { preserveScroll: true });
}

function autoAssignTeams() {
    router.post(`${base}/${props.match.id}/auto-assign`, {}, { preserveScroll: true });
}

function completeMatch() {
    router.post(`${base}/${props.match.id}/complete`);
}
</script>

<template>
    <Head :title="`Live: ${match.title}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <!-- Header -->
            <div class="mb-4 flex items-center justify-between">
                <Link :href="base" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="size-4" />Partidos
                </Link>
                <RefreshCw class="size-4 text-muted-foreground" />
            </div>

            <!-- Score -->
            <div class="mb-6 text-center">
                <Badge variant="outline" class="mb-2">{{ statusLabel[match.status] ?? match.status }}</Badge>
                <h1 class="text-lg font-medium">{{ match.title }}</h1>

                <div class="mt-4 flex items-center justify-center gap-6">
                    <div class="text-center">
                        <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ match.team_a_name }}</p>
                        <p class="text-5xl font-bold">{{ teamAGoals }}</p>
                    </div>
                    <span class="text-2xl text-muted-foreground">-</span>
                    <div class="text-center">
                        <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ match.team_b_name }}</p>
                        <p class="text-5xl font-bold">{{ teamBGoals }}</p>
                    </div>
                </div>

                <Button v-if="match.status === 'in_progress'" variant="outline" size="sm" class="mt-4" @click="autoAssignTeams">
                    <Shuffle class="mr-2 size-4" />Sortear Equipos
                </Button>
            </div>

            <!-- Formations -->
            <div class="mb-6">
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Formacion</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="mb-2 text-center text-sm font-semibold">{{ match.team_a_name }}</p>
                        <div class="space-y-1">
                            <div v-for="att in teamAPlayers" :key="att.id" class="flex items-center gap-2 rounded-md bg-accent p-2">
                                <span class="flex size-6 items-center justify-center rounded-full bg-muted text-xs font-bold">{{ att.player?.name?.charAt(0) }}</span>
                                <span class="truncate text-sm">{{ att.player?.name }}</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <p class="mb-2 text-center text-sm font-semibold">{{ match.team_b_name }}</p>
                        <div class="space-y-1">
                            <div v-for="att in teamBPlayers" :key="att.id" class="flex items-center gap-2 rounded-md bg-accent p-2">
                                <span class="flex size-6 items-center justify-center rounded-full bg-muted text-xs font-bold">{{ att.player?.name?.charAt(0) }}</span>
                                <span class="truncate text-sm">{{ att.player?.name }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Register event -->
            <div class="mb-6">
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Registrar evento</h3>

                <div class="mb-3 grid grid-cols-2 gap-2">
                    <div>
                        <Label class="sr-only">Player</Label>
                        <Select v-model="selectedPlayerId">
                            <SelectTrigger><SelectValue placeholder="Jugador" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="player in players" :key="player.id" :value="String(player.id)">
                                    {{ player.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label class="sr-only">Minute</Label>
                        <Input v-model.number="minute" type="number" min="0" max="200" placeholder="Minuto" />
                    </div>
                </div>

                <div class="grid grid-cols-4 gap-2">
                    <button
                        v-for="qe in quickEvents"
                        :key="qe.value"
                        class="flex flex-col items-center justify-center gap-1 rounded-lg border border-border p-3 text-center transition-colors hover:bg-accent"
                        :class="{ 'text-yellow-400': qe.value === 'yellow_card', 'text-destructive': qe.value === 'red_card', 'text-primary': qe.value === 'goal' || qe.value === 'assist' }"
                        @click="recordQuickEvent(qe.value)"
                    >
                        <component :is="qe.icon" class="size-5" />
                        <span class="text-xs">{{ qe.label }}</span>
                    </button>
                </div>

                <button
                    class="mt-2 w-full text-center text-sm text-muted-foreground hover:text-foreground"
                    @click="showMoreEvents = !showMoreEvents"
                >
                    {{ showMoreEvents ? 'Menos eventos' : 'Mas eventos' }} &#9662;
                </button>

                <div v-if="showMoreEvents" class="mt-2 flex gap-2">
                    <Select v-model="selectedEventType" class="flex-1">
                        <SelectTrigger><SelectValue placeholder="Tipo de evento" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="et in allEventTypes" :key="et.value" :value="et.value">
                                {{ et.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Button @click="recordEvent">Registrar</Button>
                </div>
            </div>

            <!-- Events list -->
            <div>
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    Eventos ({{ match.events?.length ?? 0 }})
                </h3>
                <div v-if="sortedEvents.length" class="space-y-2">
                    <div v-for="event in sortedEvents" :key="event.id" class="flex items-start justify-between rounded-lg border border-border p-3">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 text-sm font-medium text-muted-foreground">{{ event.minute }}'</span>
                            <CircleDot v-if="event.event_type === 'goal'" class="mt-0.5 size-4 text-primary" />
                            <RectangleVertical v-else-if="event.event_type === 'yellow_card'" class="mt-0.5 size-4 text-yellow-400" />
                            <RectangleVertical v-else-if="event.event_type === 'red_card'" class="mt-0.5 size-4 text-destructive" />
                            <CircleDot v-else class="mt-0.5 size-4 text-muted-foreground" />
                            <div>
                                <p class="font-medium">{{ event.player?.name }}</p>
                                <p class="text-sm text-muted-foreground">{{ eventLabel[event.event_type] ?? event.event_type }}</p>
                            </div>
                        </div>
                        <button class="text-destructive hover:text-destructive/80" @click="removeEvent(event.id)">
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">No hay eventos registrados.</p>
            </div>

            <!-- End match -->
            <div v-if="match.status === 'in_progress'" class="mt-6 border-t border-border pt-4">
                <Button variant="outline" class="w-full" @click="completeMatch">Terminar partido</Button>
            </div>
        </div>
    </AppLayout>
</template>
