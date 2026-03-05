<script setup lang="ts">
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import {
    ArrowDownRight,
    Ban,
    CalendarPlus,
    Check,
    EllipsisVertical,
    Gamepad2,
    Lock,
    Play,
    MapPin,
    Navigation,
    Pencil,
    Shield,
    Trash2,
    Undo2,
    UserMinus,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, FootballMatch, Player } from '@/types';

type PaginatedPlayers = {
    data: Player[];
};

type Props = {
    club: Club;
    match: FootballMatch;
    players: Player[];
    isAdmin: boolean;
    myPlayer: Player | null;
    unregisteredPlayers: PaginatedPlayers | null;
};
const props = defineProps<Props>();

const base = `/clubs/${props.club.id}/matches/${props.match.id}`;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.id}` },
    { title: 'Partidos', href: `/clubs/${props.club.id}/matches` },
    { title: props.match.title, href: base },
];

// --- Reactive clock ---
const now = ref(Date.now());
let timer: ReturnType<typeof setInterval>;
onMounted(() => { timer = setInterval(() => { now.value = Date.now(); }, 1000); });
onBeforeUnmount(() => clearInterval(timer));

// --- Status ---
const statusLabel: Record<string, string> = {
    upcoming: 'Proximo',
    in_progress: 'En juego',
    completed: 'Finalizado',
    cancelled: 'Cancelado',
};

const statusColor: Record<string, string> = {
    upcoming: 'bg-emerald-600 text-white',
    in_progress: 'bg-orange-500 text-white',
    completed: 'bg-blue-600 text-white',
    cancelled: 'bg-muted text-muted-foreground',
};

// --- Date helpers ---
const scheduledDate = new Date(props.match.scheduled_at);

const formattedDate = computed(() => {
    return scheduledDate.toLocaleDateString('es', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }).replace(/^\w/, c => c.toUpperCase());
});

const formattedTime = computed(() => {
    return scheduledDate.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit', hour12: false });
});

const arrivalTime = computed(() => {
    const d = new Date(scheduledDate);
    d.setMinutes(d.getMinutes() - props.match.arrival_minutes);
    return d.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit', hour12: false });
});

// --- Registration gate ---
const registrationOpensAt = computed(() => {
    const d = new Date(scheduledDate);
    d.setHours(d.getHours() - props.match.registration_opens_hours);
    return d.getTime();
});

const isRegistrationOpen = computed(() => now.value >= registrationOpensAt.value);

const registrationCountdown = computed(() => {
    if (isRegistrationOpen.value) return null;
    const diff = registrationOpensAt.value - now.value;
    if (diff <= 0) return null;
    const totalSecs = Math.floor(diff / 1000);
    return {
        days: Math.floor(totalSecs / 86400),
        hours: Math.floor((totalSecs % 86400) / 3600),
        minutes: Math.floor((totalSecs % 3600) / 60),
        seconds: totalSecs % 60,
    };
});

// --- Attendance ---
const confirmedAttendances = computed(() =>
    props.match.attendances?.filter(a => a.status === 'confirmed') ?? [],
);

const starters = computed(() => confirmedAttendances.value.filter(a => a.role === 'starter'));
const substitutes = computed(() => confirmedAttendances.value.filter(a => a.role === 'substitute'));
const pendingRole = computed(() => confirmedAttendances.value.filter(a => a.role === 'pending'));
const declined = computed(() => props.match.attendances?.filter(a => a.status === 'declined') ?? []);

const confirmedCount = computed(() => confirmedAttendances.value.length);
const substituteCount = computed(() => substitutes.value.length);
const startersAndPending = computed(() => confirmedCount.value - substituteCount.value);

const totalSlots = computed(() => props.match.max_players + props.match.max_substitutes);
const fillPercentage = computed(() =>
    totalSlots.value > 0 ? Math.min(100, Math.round((confirmedCount.value / totalSlots.value) * 100)) : 0,
);

const progressColor = computed(() => {
    if (fillPercentage.value >= 100) return 'bg-red-500';
    if (fillPercentage.value >= 75) return 'bg-orange-500';
    return 'bg-emerald-500';
});

// --- Team grouping ---
const teamAStarters = computed(() => starters.value.filter(a => a.team === 'a'));
const teamBStarters = computed(() => starters.value.filter(a => a.team === 'b'));
const unassignedStarters = computed(() => [...starters.value.filter(a => !a.team), ...pendingRole.value]);
const hasTeamAssignments = computed(() => teamAStarters.value.length > 0 || teamBStarters.value.length > 0);

const teamAConfirmed = computed(() => confirmedAttendances.value.filter(a => a.team === 'a'));
const teamBConfirmed = computed(() => confirmedAttendances.value.filter(a => a.team === 'b'));
const unassignedTeam = computed(() => confirmedAttendances.value.filter(a => !a.team));

// --- My attendance ---
const myAttendance = computed(() => {
    if (!props.myPlayer) return null;
    return props.match.attendances?.find(a => a.player_id === props.myPlayer!.id) ?? null;
});

const myStatus = computed(() => myAttendance.value?.status ?? null);

const myRoleLabel = computed(() => {
    if (!myAttendance.value || myStatus.value !== 'confirmed') return null;
    const role = myAttendance.value.role;
    if (role === 'starter') return 'Titular';
    if (role === 'substitute') return 'Suplente';
    return 'Sin asignar';
});

// --- Admin: unregistered players ---
const isFull = computed(() => confirmedCount.value >= totalSlots.value);

// --- Team selection dialog ---
const showTeamDialog = ref(false);

function confirmWithTeam(team: 'a' | 'b' | null) {
    if (!props.myPlayer) return;
    router.post(`${base}/attendance`, {
        player_id: props.myPlayer.id,
        status: 'confirmed',
        team,
    }, {
        onSuccess: () => { showTeamDialog.value = false; },
    });
}

// --- Actions ---
function registerPlayer(playerId: number, status: string) {
    router.post(`${base}/attendance`, { player_id: playerId, status });
}

function confirmAttendance() {
    showTeamDialog.value = true;
}

function declineAttendance() {
    if (props.myPlayer) registerPlayer(props.myPlayer.id, 'declined');
}

function adminRemoveFromMatch(attendanceId: number) {
    router.delete(`${base}/attendance/${attendanceId}`, { preserveScroll: true });
}

function adminMarkDeclined(attendanceId: number) {
    router.patch(`${base}/attendance/${attendanceId}`, { status: 'declined' }, { preserveScroll: true });
}

function adminReconfirm(attendanceId: number) {
    router.patch(`${base}/attendance/${attendanceId}`, { status: 'confirmed' }, { preserveScroll: true });
}

function startMatch() { router.post(`${base}/start`); }
function cancelMatch() { router.post(`${base}/cancel`); }
function finalizeStats() { router.post(`${base}/finalize-stats`); }

const showDeleteDialog = ref(false);
function deleteMatch() {
    router.delete(base, {
        onSuccess: () => { showDeleteDialog.value = false; },
    });
}

// --- Team display helpers ---
function teamLabel(team: 'a' | 'b' | null): string {
    if (team === 'a') return props.match.team_a_name;
    if (team === 'b') return props.match.team_b_name;
    return '';
}

function teamColor(team: 'a' | 'b' | null): string | null {
    if (team === 'a') return props.match.team_a_color;
    if (team === 'b') return props.match.team_b_color;
    return null;
}

// --- Calendar ---
function googleCalendarUrl(): string {
    const start = scheduledDate.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
    const endDate = new Date(scheduledDate);
    endDate.setMinutes(endDate.getMinutes() + props.match.duration_minutes);
    const end = endDate.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
    const details = props.match.notes ?? '';
    const location = props.match.field?.venue?.address ?? '';
    return `https://calendar.google.com/calendar/event?action=TEMPLATE&text=${encodeURIComponent(props.match.title)}&dates=${start}/${end}&details=${encodeURIComponent(details)}&location=${encodeURIComponent(location)}`;
}

function pad(n: number): string {
    return String(n).padStart(2, '0');
}
</script>

<template>
    <Head :title="match.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <!-- Header Card -->
            <div class="rounded-xl border border-border bg-card p-5 text-center">
                <div class="mb-3 flex items-center justify-between">
                    <span :class="['rounded px-2 py-0.5 text-xs font-bold uppercase', statusColor[match.status]]">
                        {{ statusLabel[match.status] ?? match.status }}
                    </span>
                    <div class="flex items-center gap-2">
                        <Link v-if="isAdmin && (match.status === 'upcoming' || match.status === 'in_progress')" :href="`${base}/edit`">
                            <Button variant="ghost" size="icon" class="size-8">
                                <Pencil class="size-4" />
                            </Button>
                        </Link>
                    </div>
                </div>
                <h1 class="text-xl font-bold">{{ match.title }}</h1>
                <p class="text-sm text-muted-foreground">{{ formattedDate }}</p>
                <p class="mt-1 text-3xl font-bold tracking-tight">{{ formattedTime }}</p>
            </div>

            <!-- Quick Stats -->
            <div class="mt-4 grid grid-cols-4 divide-x divide-border rounded-xl border border-border bg-card">
                <div class="px-2 py-3 text-center">
                    <p class="text-lg font-bold text-emerald-500">{{ startersAndPending }}/{{ match.max_players }}</p>
                    <p class="text-xs text-muted-foreground">Confirmados</p>
                </div>
                <div class="px-2 py-3 text-center">
                    <p class="text-lg font-bold text-amber-500">{{ substituteCount }}/{{ match.max_substitutes }}</p>
                    <p class="text-xs text-muted-foreground">Suplentes</p>
                </div>
                <div class="px-2 py-3 text-center">
                    <p class="text-lg font-bold">{{ match.duration_minutes }}'</p>
                    <p class="text-xs text-muted-foreground">Duracion</p>
                </div>
                <div class="px-2 py-3 text-center">
                    <p class="text-lg font-bold">{{ arrivalTime }}</p>
                    <p class="text-xs text-muted-foreground">Llegada</p>
                </div>
            </div>

            <!-- Team Matchup (Champions League style) -->
            <div v-if="hasTeamAssignments" class="mt-4 overflow-hidden rounded-xl border border-border bg-card">
                <div class="flex items-center gap-3 border-b border-border/50 px-4 py-3">
                    <span
                        class="size-4 shrink-0 rounded-sm"
                        :style="match.team_a_color ? { backgroundColor: match.team_a_color } : {}"
                    />
                    <span class="min-w-0 flex-1 truncate font-semibold">{{ match.team_a_name }}</span>
                    <span class="text-2xl font-bold">{{ teamAConfirmed.length }}</span>
                </div>
                <div class="flex items-center justify-center py-1">
                    <span class="text-xs font-bold tracking-widest text-muted-foreground">VS</span>
                </div>
                <div class="flex items-center gap-3 border-t border-border/50 px-4 py-3">
                    <span
                        class="size-4 shrink-0 rounded-sm"
                        :style="match.team_b_color ? { backgroundColor: match.team_b_color } : {}"
                    />
                    <span class="min-w-0 flex-1 truncate font-semibold">{{ match.team_b_name }}</span>
                    <span class="text-2xl font-bold">{{ teamBConfirmed.length }}</span>
                </div>
                <div v-if="unassignedTeam.length" class="border-t border-border bg-muted/30 px-4 py-2 text-center text-xs text-muted-foreground">
                    {{ unassignedTeam.length }} sin equipo
                </div>
            </div>

            <!-- Venue & Field -->
            <div v-if="match.field" class="mt-4 rounded-xl border border-border bg-card p-4">
                <div class="flex items-start gap-3">
                    <MapPin class="mt-0.5 size-5 shrink-0 text-muted-foreground" />
                    <div>
                        <p class="font-medium">
                            {{ match.field.name }}
                            <span class="ml-1.5 rounded bg-emerald-600/20 px-1.5 py-0.5 text-xs font-semibold text-emerald-500">
                                {{ match.field.field_type }}
                            </span>
                        </p>
                        <p v-if="match.field.venue" class="text-sm text-muted-foreground">{{ match.field.venue.name }}</p>
                        <p v-if="match.field.venue?.address" class="text-sm text-muted-foreground">{{ match.field.venue.address }}</p>
                        <a
                            v-if="match.field.venue?.map_link"
                            :href="match.field.venue.map_link"
                            target="_blank"
                            rel="noopener"
                            class="mt-1.5 inline-flex items-center gap-1 text-sm font-medium text-emerald-500 hover:underline"
                        >
                            <Navigation class="size-3.5" />
                            Ver en mapa
                        </a>
                    </div>
                </div>
            </div>

            <!-- Capacity Bar -->
            <div class="mt-4 rounded-xl border border-border bg-card p-4">
                <div class="mb-2 flex items-center justify-between text-sm">
                    <span class="text-muted-foreground">Cupos ocupados</span>
                    <span class="font-medium">{{ confirmedCount }}/{{ totalSlots }}</span>
                </div>
                <div class="h-2.5 w-full overflow-hidden rounded-full bg-muted">
                    <div
                        :class="['h-full rounded-full transition-all duration-500', progressColor]"
                        :style="{ width: `${fillPercentage}%` }"
                    />
                </div>
            </div>

            <!-- Start Match Button -->
            <Button
                v-if="isAdmin && match.status === 'upcoming'"
                class="mt-4 w-full gap-2 bg-orange-500 hover:bg-orange-600"
                @click="startMatch"
            >
                <Play class="size-4" />
                Iniciar partido
            </Button>

            <!-- Admin Panel Button -->
            <Link
                v-if="isAdmin && (match.status === 'upcoming' || match.status === 'in_progress')"
                :href="`${base}/live`"
                class="mt-4 block"
            >
                <Button class="w-full gap-2 bg-emerald-600 hover:bg-emerald-700">
                    <Gamepad2 class="size-4" />
                    Panel de control
                </Button>
            </Link>

            <!-- Registration Countdown (closed) -->
            <div
                v-if="myPlayer && !isRegistrationOpen && registrationCountdown && match.status === 'upcoming'"
                class="mt-4 rounded-xl border border-border bg-card p-5 text-center"
            >
                <Lock class="mx-auto mb-2 size-6 text-muted-foreground" />
                <p class="mb-4 text-sm text-muted-foreground">La confirmacion de asistencia se abre en</p>
                <div class="flex items-center justify-center gap-1">
                    <template v-if="registrationCountdown.days > 0">
                        <div class="rounded-lg border border-border bg-muted/50 px-3 py-2">
                            <p class="text-xl font-bold">{{ pad(registrationCountdown.days) }}</p>
                            <p class="text-[10px] uppercase text-muted-foreground">Dias</p>
                        </div>
                        <span class="text-xl font-bold text-muted-foreground">:</span>
                    </template>
                    <div class="rounded-lg border border-border bg-muted/50 px-3 py-2">
                        <p class="text-xl font-bold">{{ pad(registrationCountdown.hours) }}</p>
                        <p class="text-[10px] uppercase text-muted-foreground">Hrs</p>
                    </div>
                    <span class="text-xl font-bold text-muted-foreground">:</span>
                    <div class="rounded-lg border border-border bg-muted/50 px-3 py-2">
                        <p class="text-xl font-bold">{{ pad(registrationCountdown.minutes) }}</p>
                        <p class="text-[10px] uppercase text-muted-foreground">Min</p>
                    </div>
                    <span class="text-xl font-bold text-muted-foreground">:</span>
                    <div class="rounded-lg border border-border bg-muted/50 px-3 py-2">
                        <p class="text-xl font-bold">{{ pad(registrationCountdown.seconds) }}</p>
                        <p class="text-[10px] uppercase text-muted-foreground">Seg</p>
                    </div>
                </div>
            </div>

            <!-- Attendance Buttons (registration open) -->
            <div
                v-if="myPlayer && isRegistrationOpen && (match.status === 'upcoming' || match.status === 'in_progress')"
                class="mt-4 flex gap-3"
            >
                <Button
                    class="flex-1 gap-2"
                    :variant="myStatus === 'confirmed' ? 'default' : 'outline'"
                    :class="myStatus === 'confirmed' ? 'bg-emerald-600 hover:bg-emerald-700' : ''"
                    @click="confirmAttendance"
                >
                    <Check class="size-4" />
                    Voy
                </Button>
                <Button
                    class="flex-1 gap-2"
                    :variant="myStatus === 'declined' ? 'destructive' : 'outline'"
                    @click="declineAttendance"
                >
                    <X class="size-4" />
                    No voy
                </Button>
            </div>

            <!-- Team Selection Dialog -->
            <Dialog v-model:open="showTeamDialog">
                <DialogContent class="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Elige tu equipo</DialogTitle>
                        <DialogDescription>Selecciona en que equipo quieres jugar, o dejalo sin preferencia.</DialogDescription>
                    </DialogHeader>
                    <div class="space-y-2">
                        <button
                            class="flex w-full items-center gap-3 rounded-lg border border-border p-3 transition-colors hover:bg-accent"
                            @click="confirmWithTeam('a')"
                        >
                            <span
                                class="inline-block size-4 rounded-full border border-border"
                                :style="match.team_a_color ? { backgroundColor: match.team_a_color } : {}"
                            />
                            <span class="font-medium">{{ match.team_a_name }}</span>
                        </button>
                        <button
                            class="flex w-full items-center gap-3 rounded-lg border border-border p-3 transition-colors hover:bg-accent"
                            @click="confirmWithTeam('b')"
                        >
                            <span
                                class="inline-block size-4 rounded-full border border-border"
                                :style="match.team_b_color ? { backgroundColor: match.team_b_color } : {}"
                            />
                            <span class="font-medium">{{ match.team_b_name }}</span>
                        </button>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" class="w-full" @click="confirmWithTeam(null)">
                            Sin preferencia
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- My role alert -->
            <div
                v-if="myAttendance && myStatus === 'confirmed'"
                class="mt-3 flex items-center gap-2 rounded-xl border p-3 text-sm"
                :class="{
                    'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400': myAttendance.role === 'starter',
                    'border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400': myAttendance.role === 'substitute',
                    'border-border bg-muted/50': myAttendance.role === 'pending',
                }"
            >
                <Shield v-if="myAttendance.role === 'starter'" class="size-4" />
                <ArrowDownRight v-else-if="myAttendance.role === 'substitute'" class="size-4" />
                <Users v-else class="size-4" />
                <span>Tu rol: <strong>{{ myRoleLabel }}</strong></span>
                <template v-if="myAttendance.team">
                    <span class="text-muted-foreground">—</span>
                    <span class="flex items-center gap-1">
                        <span
                            v-if="teamColor(myAttendance.team)"
                            class="inline-block size-2.5 rounded-full"
                            :style="{ backgroundColor: teamColor(myAttendance.team)! }"
                        />
                        {{ teamLabel(myAttendance.team) }}
                    </span>
                </template>
            </div>

            <!-- No player linked warning -->
            <div
                v-if="!myPlayer && match.status === 'upcoming'"
                class="mt-4 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-center text-sm text-amber-600 dark:text-amber-400"
            >
                No tienes un jugador vinculado a este club. Pide al administrador que te vincule para confirmar asistencia.
            </div>

            <!-- Add to Calendar -->
            <a
                v-if="match.status === 'upcoming'"
                :href="googleCalendarUrl()"
                target="_blank"
                rel="noopener"
                class="mt-4 block"
            >
                <Button variant="outline" class="w-full gap-2">
                    <CalendarPlus class="size-4" />
                    Agregar al calendario
                </Button>
            </a>

            <!-- Notes -->
            <div v-if="match.notes" class="mt-4 rounded-xl border border-border bg-card p-4">
                <p class="mb-1 text-xs font-semibold uppercase text-muted-foreground">Notas</p>
                <p class="text-sm">{{ match.notes }}</p>
            </div>

            <!-- Confirmados -->
            <div class="mt-6">
                <div class="mb-3 flex items-center gap-2">
                    <Shield class="size-4 text-emerald-500" />
                    <h3 class="font-semibold">Confirmados ({{ startersAndPending }}/{{ match.max_players }})</h3>
                </div>

                <!-- Grouped by team -->
                <template v-if="hasTeamAssignments">
                    <!-- Team A Card -->
                    <div v-if="teamAStarters.length" class="mb-4 overflow-hidden rounded-xl border border-border">
                        <div class="flex items-center gap-2.5 px-4 py-2.5" :style="match.team_a_color ? { backgroundColor: match.team_a_color + '20' } : {}">
                            <span
                                class="size-4 shrink-0 rounded-sm"
                                :style="match.team_a_color ? { backgroundColor: match.team_a_color } : {}"
                            />
                            <span class="flex-1 text-sm font-bold">{{ match.team_a_name }}</span>
                            <span class="text-xs text-muted-foreground">{{ teamAStarters.length }} jugadores</span>
                        </div>
                        <div class="divide-y divide-border/50">
                            <div
                                v-for="(att, idx) in teamAStarters"
                                :key="att.id"
                                class="flex items-center gap-3 bg-card px-4 py-2.5"
                            >
                                <span class="w-5 text-center text-xs font-bold text-muted-foreground">{{ idx + 1 }}</span>
                                <div class="flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold" :style="match.team_a_color ? { backgroundColor: match.team_a_color + '30' } : { backgroundColor: 'var(--color-muted)' }">
                                    {{ att.player?.display_name?.charAt(0)?.toUpperCase() ?? '?' }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <Link v-if="att.player" :href="`/clubs/${club.id}/players/${att.player.id}`" class="block truncate text-sm font-medium hover:text-primary hover:underline">{{ att.player.display_name }}</Link>
                                    <p v-if="att.player?.position_label" class="text-xs text-muted-foreground">{{ att.player.position_label }}</p>
                                </div>
                                <DropdownMenu v-if="isAdmin && (match.status === 'upcoming' || match.status === 'in_progress')">
                                    <DropdownMenuTrigger as-child>
                                        <button class="rounded p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground">
                                            <EllipsisVertical class="size-4" />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" class="w-48">
                                        <DropdownMenuItem class="gap-2 text-destructive" @click="adminMarkDeclined(att.id)">
                                            <UserMinus class="size-4" />
                                            No asiste
                                        </DropdownMenuItem>
                                        <DropdownMenuItem class="gap-2" @click="adminRemoveFromMatch(att.id)">
                                            <Undo2 class="size-4" />
                                            Quitar del partido
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>
                    </div>

                    <!-- Team B Card -->
                    <div v-if="teamBStarters.length" class="mb-4 overflow-hidden rounded-xl border border-border">
                        <div class="flex items-center gap-2.5 px-4 py-2.5" :style="match.team_b_color ? { backgroundColor: match.team_b_color + '20' } : {}">
                            <span
                                class="size-4 shrink-0 rounded-sm"
                                :style="match.team_b_color ? { backgroundColor: match.team_b_color } : {}"
                            />
                            <span class="flex-1 text-sm font-bold">{{ match.team_b_name }}</span>
                            <span class="text-xs text-muted-foreground">{{ teamBStarters.length }} jugadores</span>
                        </div>
                        <div class="divide-y divide-border/50">
                            <div
                                v-for="(att, idx) in teamBStarters"
                                :key="att.id"
                                class="flex items-center gap-3 bg-card px-4 py-2.5"
                            >
                                <span class="w-5 text-center text-xs font-bold text-muted-foreground">{{ idx + 1 }}</span>
                                <div class="flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold" :style="match.team_b_color ? { backgroundColor: match.team_b_color + '30' } : { backgroundColor: 'var(--color-muted)' }">
                                    {{ att.player?.display_name?.charAt(0)?.toUpperCase() ?? '?' }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <Link v-if="att.player" :href="`/clubs/${club.id}/players/${att.player.id}`" class="block truncate text-sm font-medium hover:text-primary hover:underline">{{ att.player.display_name }}</Link>
                                    <p v-if="att.player?.position_label" class="text-xs text-muted-foreground">{{ att.player.position_label }}</p>
                                </div>
                                <DropdownMenu v-if="isAdmin && (match.status === 'upcoming' || match.status === 'in_progress')">
                                    <DropdownMenuTrigger as-child>
                                        <button class="rounded p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground">
                                            <EllipsisVertical class="size-4" />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" class="w-48">
                                        <DropdownMenuItem class="gap-2 text-destructive" @click="adminMarkDeclined(att.id)">
                                            <UserMinus class="size-4" />
                                            No asiste
                                        </DropdownMenuItem>
                                        <DropdownMenuItem class="gap-2" @click="adminRemoveFromMatch(att.id)">
                                            <Undo2 class="size-4" />
                                            Quitar del partido
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>
                    </div>

                    <!-- Unassigned starters -->
                    <div v-if="unassignedStarters.length" class="mb-4 overflow-hidden rounded-xl border border-border">
                        <div class="flex items-center gap-2.5 bg-muted/30 px-4 py-2.5">
                            <span class="size-4 shrink-0 rounded-sm bg-muted" />
                            <span class="flex-1 text-sm font-bold text-muted-foreground">Sin equipo</span>
                            <span class="text-xs text-muted-foreground">{{ unassignedStarters.length }} jugadores</span>
                        </div>
                        <div class="divide-y divide-border/50">
                            <div
                                v-for="(att, idx) in unassignedStarters"
                                :key="att.id"
                                class="flex items-center gap-3 bg-card px-4 py-2.5"
                            >
                                <span class="w-5 text-center text-xs font-bold text-muted-foreground">{{ idx + 1 }}</span>
                                <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-bold">
                                    {{ att.player?.display_name?.charAt(0)?.toUpperCase() ?? '?' }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <Link v-if="att.player" :href="`/clubs/${club.id}/players/${att.player.id}`" class="block truncate text-sm font-medium hover:text-primary hover:underline">{{ att.player.display_name }}</Link>
                                    <p v-if="att.player?.position_label" class="text-xs text-muted-foreground">{{ att.player.position_label }}</p>
                                </div>
                                <DropdownMenu v-if="isAdmin && (match.status === 'upcoming' || match.status === 'in_progress')">
                                    <DropdownMenuTrigger as-child>
                                        <button class="rounded p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground">
                                            <EllipsisVertical class="size-4" />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" class="w-48">
                                        <DropdownMenuItem class="gap-2 text-destructive" @click="adminMarkDeclined(att.id)">
                                            <UserMinus class="size-4" />
                                            No asiste
                                        </DropdownMenuItem>
                                        <DropdownMenuItem class="gap-2" @click="adminRemoveFromMatch(att.id)">
                                            <Undo2 class="size-4" />
                                            Quitar del partido
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Flat list (no team assignments) -->
                <template v-else>
                    <div v-if="starters.length || pendingRole.length" class="overflow-hidden rounded-xl border border-border">
                        <div class="divide-y divide-border/50">
                            <div
                                v-for="(att, idx) in [...starters, ...pendingRole]"
                                :key="att.id"
                                class="flex items-center gap-3 bg-card px-4 py-2.5"
                            >
                                <span class="w-5 text-center text-xs font-bold text-muted-foreground">{{ idx + 1 }}</span>
                                <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-bold">
                                    {{ att.player?.display_name?.charAt(0)?.toUpperCase() ?? '?' }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <Link v-if="att.player" :href="`/clubs/${club.id}/players/${att.player.id}`" class="block truncate text-sm font-medium hover:text-primary hover:underline">{{ att.player.display_name }}</Link>
                                    <p v-if="att.player?.position_label" class="text-xs text-muted-foreground">{{ att.player.position_label }}</p>
                                </div>
                                <DropdownMenu v-if="isAdmin && (match.status === 'upcoming' || match.status === 'in_progress')">
                                    <DropdownMenuTrigger as-child>
                                        <button class="rounded p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground">
                                            <EllipsisVertical class="size-4" />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" class="w-48">
                                        <DropdownMenuItem class="gap-2 text-destructive" @click="adminMarkDeclined(att.id)">
                                            <UserMinus class="size-4" />
                                            No asiste
                                        </DropdownMenuItem>
                                        <DropdownMenuItem class="gap-2" @click="adminRemoveFromMatch(att.id)">
                                            <Undo2 class="size-4" />
                                            Quitar del partido
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">Nadie aun</p>
                </template>

                <p v-if="hasTeamAssignments && !teamAStarters.length && !teamBStarters.length && !unassignedStarters.length" class="text-sm text-muted-foreground">Nadie aun</p>
            </div>

            <!-- Suplentes -->
            <div class="mt-5">
                <div class="mb-3 flex items-center gap-2">
                    <ArrowDownRight class="size-4 text-amber-500" />
                    <h3 class="font-semibold">Suplentes ({{ substituteCount }}/{{ match.max_substitutes }})</h3>
                </div>
                <div v-if="substitutes.length" class="overflow-hidden rounded-xl border border-border">
                    <div class="divide-y divide-border/50">
                        <div
                            v-for="(att, idx) in substitutes"
                            :key="att.id"
                            class="flex items-center gap-3 bg-card px-4 py-2.5"
                        >
                            <span class="w-5 text-center text-xs font-bold text-muted-foreground">{{ idx + 1 }}</span>
                            <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-amber-500/20 text-xs font-bold">
                                {{ att.player?.display_name?.charAt(0)?.toUpperCase() ?? '?' }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <Link v-if="att.player" :href="`/clubs/${club.id}/players/${att.player.id}`" class="block truncate text-sm font-medium hover:text-primary hover:underline">{{ att.player.display_name }}</Link>
                                <p v-if="att.player?.position_label" class="text-xs text-muted-foreground">{{ att.player.position_label }}</p>
                            </div>
                            <DropdownMenu v-if="isAdmin && (match.status === 'upcoming' || match.status === 'in_progress')">
                                <DropdownMenuTrigger as-child>
                                    <button class="rounded p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground">
                                        <EllipsisVertical class="size-4" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-48">
                                    <DropdownMenuItem class="gap-2 text-destructive" @click="adminMarkDeclined(att.id)">
                                        <UserMinus class="size-4" />
                                        No asiste
                                    </DropdownMenuItem>
                                    <DropdownMenuItem class="gap-2" @click="adminRemoveFromMatch(att.id)">
                                        <Undo2 class="size-4" />
                                        Quitar del partido
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">Nadie aun</p>
            </div>

            <!-- No van -->
            <div v-if="declined.length" class="mt-5 opacity-60">
                <div class="mb-3 flex items-center gap-2">
                    <X class="size-4 text-red-500" />
                    <h3 class="font-semibold">No van ({{ declined.length }})</h3>
                </div>
                <div class="overflow-hidden rounded-xl border border-border">
                    <div class="divide-y divide-border/50">
                        <div
                            v-for="att in declined"
                            :key="att.id"
                            class="flex items-center gap-3 bg-card px-4 py-2.5"
                        >
                            <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-bold">
                                {{ att.player?.display_name?.charAt(0)?.toUpperCase() ?? '?' }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <Link v-if="att.player" :href="`/clubs/${club.id}/players/${att.player.id}`" class="block truncate text-sm font-medium hover:text-primary hover:underline">{{ att.player.display_name }}</Link>
                            </div>
                            <DropdownMenu v-if="isAdmin && (match.status === 'upcoming' || match.status === 'in_progress')">
                                <DropdownMenuTrigger as-child>
                                    <button class="rounded p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground">
                                        <EllipsisVertical class="size-4" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-48">
                                    <DropdownMenuItem class="gap-2" @click="adminReconfirm(att.id)">
                                        <Check class="size-4 text-emerald-500" />
                                        Confirmar
                                    </DropdownMenuItem>
                                    <DropdownMenuItem class="gap-2" @click="adminRemoveFromMatch(att.id)">
                                        <Undo2 class="size-4" />
                                        Quitar del partido
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admin: Register players manually -->
            <div
                v-if="isAdmin && (match.status === 'upcoming' || match.status === 'in_progress') && unregisteredPlayers?.data?.length"
                class="mt-6 rounded-xl border border-border bg-card p-4"
            >
                <h3 class="mb-3 font-semibold">Registrar jugadores</h3>

                <div v-if="isFull" class="mb-3 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-400">
                    Cupo lleno — {{ confirmedCount }}/{{ totalSlots }} confirmados
                </div>

                <InfiniteScroll data="unregisteredPlayers" preserve-url only-next>
                    <div class="space-y-2">
                        <div
                            v-for="player in unregisteredPlayers.data"
                            :key="player.id"
                            class="flex items-center justify-between rounded-lg border border-border px-3 py-2"
                        >
                            <div class="flex items-center gap-2">
                                <div class="flex size-7 items-center justify-center rounded-full bg-muted text-xs font-bold">
                                    {{ player.display_name.charAt(0).toUpperCase() }}
                                </div>
                                <div>
                                    <span class="text-sm">{{ player.display_name }}</span>
                                    <p v-if="player.position_label" class="text-xs text-muted-foreground">{{ player.position_label }}</p>
                                </div>
                            </div>
                            <div class="flex gap-1.5">
                                <Button size="sm" class="h-8 gap-1 bg-emerald-600 hover:bg-emerald-700" :disabled="isFull" @click="registerPlayer(player.id, 'confirmed')">
                                    <Check class="size-3.5" />
                                    <span class="hidden sm:inline">Confirmar</span>
                                </Button>
                                <Button size="sm" variant="outline" class="h-8 gap-1" @click="registerPlayer(player.id, 'declined')">
                                    <X class="size-3.5" />
                                    <span class="hidden sm:inline">Rechazar</span>
                                </Button>
                            </div>
                        </div>
                    </div>

                    <template #loading>
                        <div class="flex justify-center py-3">
                            <div class="size-5 animate-spin rounded-full border-2 border-muted-foreground border-t-transparent" />
                        </div>
                    </template>
                </InfiniteScroll>
            </div>

            <!-- Events -->
            <div v-if="match.events?.length" class="mt-6 overflow-hidden rounded-xl border border-border">
                <div class="bg-muted/30 px-4 py-2.5">
                    <h3 class="text-sm font-bold">Eventos ({{ match.events.length }})</h3>
                </div>
                <div class="divide-y divide-border/50">
                    <div
                        v-for="event in match.events"
                        :key="event.id"
                        class="flex items-center gap-3 bg-card px-4 py-2.5"
                    >
                        <span class="w-8 text-right text-xs font-bold text-muted-foreground">{{ event.minute }}'</span>
                        <div class="min-w-0 flex-1">
                            <Link
                                v-if="event.player"
                                :href="`/clubs/${club.id}/players/${event.player.id}`"
                                class="block truncate text-sm font-medium hover:text-primary hover:underline"
                            >{{ event.player.display_name }}</Link>
                        </div>
                        <Badge variant="outline" class="shrink-0 text-xs">{{ event.event_type.replace(/_/g, ' ') }}</Badge>
                    </div>
                </div>
            </div>

            <!-- Admin: Cancel + Delete -->
            <div v-if="isAdmin && (match.status === 'upcoming' || match.status === 'in_progress')" class="mt-6 flex gap-2">
                <Button variant="outline" class="flex-1 gap-2" @click="cancelMatch">
                    <Ban class="size-4" />
                    Cancelar partido
                </Button>
                <Dialog v-model:open="showDeleteDialog">
                    <DialogTrigger as-child>
                        <Button variant="destructive" class="flex-1 gap-2">
                            <Trash2 class="size-4" />
                            Eliminar
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Eliminar partido</DialogTitle>
                            <DialogDescription>
                                Esta accion no se puede deshacer. Se eliminara el partido
                                <strong>"{{ match.title }}"</strong> junto con toda su informacion
                                de asistencia y eventos.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter class="gap-2 sm:gap-0">
                            <DialogClose as-child>
                                <Button variant="outline">Cancelar</Button>
                            </DialogClose>
                            <Button variant="destructive" class="gap-2" @click="deleteMatch">
                                <Trash2 class="size-4" />
                                Eliminar partido
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>

            <!-- Admin: Actions for completed -->
            <div v-if="isAdmin && match.status === 'completed'" class="mt-6 space-y-2">
                <div class="flex gap-2">
                    <Link :href="`${base}/summary`" class="flex-1">
                        <Button variant="outline" class="w-full">Ver resumen</Button>
                    </Link>
                    <Button
                        v-if="!match.stats_finalized_at"
                        class="flex-1"
                        @click="finalizeStats"
                    >
                        Finalizar estadisticas
                    </Button>
                    <Badge v-else variant="secondary" class="flex-1 justify-center py-2">Estadisticas finalizadas</Badge>
                </div>
                <Dialog v-model:open="showDeleteDialog">
                    <DialogTrigger as-child>
                        <Button variant="destructive" class="w-full gap-2">
                            <Trash2 class="size-4" />
                            Eliminar partido
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Eliminar partido</DialogTitle>
                            <DialogDescription>
                                Esta accion no se puede deshacer. Se eliminara el partido
                                <strong>"{{ match.title }}"</strong> junto con toda su informacion
                                de asistencia y eventos.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter class="gap-2 sm:gap-0">
                            <DialogClose as-child>
                                <Button variant="outline">Cancelar</Button>
                            </DialogClose>
                            <Button variant="destructive" class="gap-2" @click="deleteMatch">
                                <Trash2 class="size-4" />
                                Eliminar partido
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>

            <!-- Admin: Delete for cancelled -->
            <div v-if="isAdmin && match.status === 'cancelled'" class="mt-6">
                <Dialog v-model:open="showDeleteDialog">
                    <DialogTrigger as-child>
                        <Button variant="destructive" class="w-full gap-2">
                            <Trash2 class="size-4" />
                            Eliminar partido
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Eliminar partido</DialogTitle>
                            <DialogDescription>
                                Esta accion no se puede deshacer. Se eliminara el partido
                                <strong>"{{ match.title }}"</strong> junto con toda su informacion
                                de asistencia y eventos.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter class="gap-2 sm:gap-0">
                            <DialogClose as-child>
                                <Button variant="outline">Cancelar</Button>
                            </DialogClose>
                            <Button variant="destructive" class="gap-2" @click="deleteMatch">
                                <Trash2 class="size-4" />
                                Eliminar partido
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>

            <!-- Public link -->
            <div v-if="match.share_token" class="mt-4 text-center">
                <Link :href="`/match/${match.share_token}`" class="text-sm text-muted-foreground hover:underline">
                    Ver pagina publica del partido
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
