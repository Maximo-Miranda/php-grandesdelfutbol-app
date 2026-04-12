<script setup lang="ts">
import { Head, InfiniteScroll, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeftRight,
    Calendar,
    Check,
    CircleDot,
    Clock,
    CornerDownRight,
    Crosshair,
    Download,
    Droplets,
    Eye,
    Film,
    Flag,
    Hand,
    MapPin,
    Maximize,
    Minimize,
    Pause,
    Pencil,
    Play,
    Plus,
    RectangleVertical,
    RefreshCw,
    Search,
    Share2,
    Shield,
    Star,
    Timer,
    Trash2,
    Trophy,
    UserMinus,
    UserPlus,
    Users,
    Video,
    X,
    Copy,
    ExternalLink,
    Loader2,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import DrivePlayer from '@/components/match/DrivePlayer.vue';
import EventIcon from '@/components/match/EventIcon.vue';
import EventTimeline from '@/components/match/EventTimeline.vue';
import EventTypeGrid from '@/components/match/EventTypeGrid.vue';
import LiveScoreboard from '@/components/match/LiveScoreboard.vue';
import MinuteSecondInput from '@/components/match/MinuteSecondInput.vue';
import PlayerSelector from '@/components/match/PlayerSelector.vue';
import VideoPlayer from '@/components/match/VideoPlayer.vue';
import VideoUploader from '@/components/match/VideoUploader.vue';
import YouTubePlayer from '@/components/match/YouTubePlayer.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useVideoSync } from '@/composables/useVideoSync';
import AppLayout from '@/layouts/AppLayout.vue';
import { EVENT_LABELS, EVENT_ICON_COLORS, countTeamGoals as countTeamGoalsUtil } from '@/lib/match-events';
import { formatDate, formatTime, formatEventTime, getCsrfToken } from '@/lib/utils';
import type { BreadcrumbItem, Club, FootballMatch, MatchEvent, MatchReel, Player } from '@/types';

type PositionOption = { value: string; label: string };
type PaginatedReels = { data: MatchReel[] };
type Props = { club: Club; match: FootballMatch; isAdmin?: boolean; players?: Player[]; positions?: PositionOption[]; myPlayer?: Player | null; reels?: PaginatedReels; s3VideoUrl?: string | null };
const props = defineProps<Props>();

const base = `/clubs/${props.club.ulid}/matches`;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubes', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
    { title: 'Partidos', href: base },
    { title: props.match.title, href: `${base}/${props.match.ulid}` },
];

// --- Goals ---
const teamAGoals = computed(() => props.match.team_a_score ?? countTeamGoalsUtil(props.match, 'a'));
const teamBGoals = computed(() => props.match.team_b_score ?? countTeamGoalsUtil(props.match, 'b'));

// --- Manual score editing ---
const editingScore = ref(false);
const scoreForm = useForm({
    team_a_score: 0,
    team_b_score: 0,
});

function startEditingScore() {
    scoreForm.team_a_score = teamAGoals.value;
    scoreForm.team_b_score = teamBGoals.value;
    editingScore.value = true;
}

function saveScore() {
    scoreForm.patch(`/clubs/${props.club.ulid}/matches/${props.match.ulid}/score`, {
        preserveScroll: true,
        onSuccess: () => { editingScore.value = false; },
    });
}

// --- Events ---
const sortedEvents = computed(() => [...(props.match.events ?? [])].sort((a, b) => a.minute - b.minute || a.second - b.second));
const sortedEventsDesc = computed(() => [...(props.match.events ?? [])].sort((a, b) => b.minute - a.minute || b.second - a.second));

function getPlayerTeam(playerId: number | null): 'a' | 'b' | null {
    if (!playerId) return null;
    const att = props.match.attendances?.find(a => a.player_id === playerId);
    return att?.team ?? null;
}

function getEventTeam(event: MatchEvent): 'a' | 'b' | null {
    return event.team ?? getPlayerTeam(event.player_id);
}

// --- Player stats ---
type PlayerStat = {
    name: string;
    ulid: string;
    team: 'a' | 'b' | null;
    jerseyNumber: number | null;
    goals: number;
    assists: number;
    yellowCards: number;
    redCards: number;
};

const playerStats = computed(() => {
    const map = new Map<number, PlayerStat>();
    for (const event of props.match.events ?? []) {
        if (!event.player_id) continue;
        if (!map.has(event.player_id)) {
            const att = props.match.attendances?.find(a => a.player_id === event.player_id);
            map.set(event.player_id, {
                name: event.player?.display_name ?? 'Unknown',
                ulid: event.player?.ulid ?? '',
                team: att?.team as 'a' | 'b' | null ?? null,
                jerseyNumber: event.player?.jersey_number ?? null,
                goals: 0,
                assists: 0,
                yellowCards: 0,
                redCards: 0,
            });
        }
        const stat = map.get(event.player_id)!;
        if (event.event_type === 'goal') stat.goals++;
        if (event.event_type === 'assist') stat.assists++;
        if (event.event_type === 'yellow_card') stat.yellowCards++;
        if (event.event_type === 'red_card') stat.redCards++;
    }
    return map;
});

const teamAStats = computed(() =>
    [...playerStats.value.entries()].filter(([, s]) => s.team === 'a').map(([id, s]) => ({ id, ...s })),
);

const teamBStats = computed(() =>
    [...playerStats.value.entries()].filter(([, s]) => s.team === 'b').map(([id, s]) => ({ id, ...s })),
);

// --- Top scorer ---
const topScorer = computed(() => {
    let best: { name: string; goals: number; team: 'a' | 'b' | null } | null = null;
    for (const [, stat] of playerStats.value) {
        if (stat.goals > 0 && (!best || stat.goals > best.goals)) {
            best = { name: stat.name, goals: stat.goals, team: stat.team };
        }
    }
    return best;
});

// --- Date helpers ---
const formattedDate = computed(() =>
    formatDate(props.match.scheduled_at, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
        .replace(/^\w/, c => c.toUpperCase()),
);

const formattedTime = computed(() =>
    formatTime(props.match.scheduled_at),
);

const matchDuration = computed(() => {
    if (props.match.started_at && props.match.ended_at) {
        const start = new Date(props.match.started_at).getTime();
        const end = new Date(props.match.ended_at).getTime();
        return Math.round((end - start) / 60000);
    }
    return props.match.duration_minutes;
});

// --- Team color helpers ---
function teamColor(team: 'a' | 'b' | null): string {
    if (team === 'a') return props.match.team_a_color ?? '#6b7280';
    if (team === 'b') return props.match.team_b_color ?? '#6b7280';
    return '#6b7280';
}

function finalizeStats() {
    router.post(`${base}/${props.match.ulid}/finalize-stats`);
}

const showDeleteDialog = ref(false);
function deleteMatch() {
    router.delete(`${base}/${props.match.ulid}`, {
        onSuccess: () => { showDeleteDialog.value = false; },
    });
}

// --- Video Upload state ---
const hasVideoReady = computed(() => props.match.video_upload?.status === 'ready');
const hasVideoEncoding = computed(() => props.match.video_upload?.status === 'encoding');
const hasVideoAvailable = computed(() => hasVideoReady.value || hasVideoEncoding.value);
const hasYouTube = computed(() => !!props.match.video_upload?.youtube_video_id);
const youtubeVideoId = computed(() => props.match.video_upload?.youtube_video_id ?? null);
const youtubeUrl = computed(() => props.match.video_upload?.youtube_url ?? null);
const videoStreamUrl = computed(() => props.match.video_upload?.video_stream_url ?? null);
const driveViewUrl = computed(() => {
    const fileId = props.match.video_upload?.drive_file_id;
    return fileId ? `https://drive.google.com/file/d/${fileId}/view` : null;
});
const videoEmbedUrl = computed(() => {
    // Prefer YouTube embed when available
    if (props.match.video_upload?.youtube_embed_url) {
        return props.match.video_upload.youtube_embed_url;
    }
    const url = props.match.video_upload?.embed_url;
    if (!url) return null;
    return `${url}?autoplay=false&preload=false`;
});
const deletingVideo = ref(false);
const showDeleteVideoDialog = ref(false);
const showFinalizeDialog = ref(false);
const copiedLink = ref(false);

function csrfHeaders(): Record<string, string> {
    return { 'X-XSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json' };
}

const generatingShareLink = ref(false);
const shareLink = ref('');
const copiedShareLink = ref(false);
const copiedPublicLink = ref(false);

function copyPublicLink() {
    const url = `${window.location.origin}/match/${props.match.share_token}`;
    navigator.clipboard.writeText(url).then(() => {
        copiedPublicLink.value = true;
        setTimeout(() => { copiedPublicLink.value = false; }, 3000);
    });
}

async function generateShareLink() {
    generatingShareLink.value = true;
    try {
        const res = await fetch(`${base}/${props.match.ulid}/video-upload/share-link`, {
            method: 'POST',
            headers: { ...csrfHeaders(), 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ hours: 2 }),
        });
        if (res.ok) {
            const data = await res.json();
            shareLink.value = data.url;
            await navigator.clipboard.writeText(data.url);
            copiedShareLink.value = true;
            setTimeout(() => { copiedShareLink.value = false; }, 3000);
        }
    } finally {
        generatingShareLink.value = false;
    }
}

function copyVideoLink() {
    const url = youtubeUrl.value || driveViewUrl.value;
    if (!url) return;
    navigator.clipboard.writeText(url).then(() => {
        copiedLink.value = true;
        setTimeout(() => { copiedLink.value = false; }, 2000);
    });
}

function confirmDeleteVideo() {
    deletingVideo.value = true;
    fetch(`${base}/${props.match.ulid}/video-upload`, {
        method: 'DELETE',
        headers: csrfHeaders(),
        credentials: 'same-origin',
    }).then(() => {
        showDeleteVideoDialog.value = false;
        router.reload();
    }).finally(() => {
        deletingVideo.value = false;
    });
}

function formatStatsDate(dateStr: string): string {
    return formatDate(dateStr, { day: 'numeric', month: 'short', year: 'numeric' });
}

// ===== TABS =====
const activeTab = ref<'resumen' | 'eventos' | 'jugadores' | 'reels'>('resumen');

// ===== TAB: EVENTOS — event flow (adapted from Live.vue, no clock/poll) =====
type EventScope = 'player' | 'team' | 'neutral';
const eventScopes: Record<string, EventScope> = {
    goal: 'player', assist: 'player', yellow_card: 'player', red_card: 'player',
    penalty_scored: 'player', penalty_missed: 'player', own_goal: 'player',
    save: 'player', free_kick: 'player', substitution: 'player', injury: 'player',
    foul: 'player', handball: 'player',
    shot_on_target: 'team', corner_kick: 'team', throw_in: 'team', offside: 'team',
    timeout: 'neutral', ball_touched_referee: 'neutral', stoppage_start: 'neutral',
    stoppage_end: 'neutral', water_break: 'neutral',
};

const allEventTypes = [
    { value: 'goal', label: 'Gol', icon: CircleDot, color: 'text-emerald-400', bg: 'bg-emerald-500/10 border-emerald-500/30 hover:bg-emerald-500/20' },
    { value: 'assist', label: 'Asist.', icon: CircleDot, color: 'text-sky-400', bg: 'bg-sky-500/10 border-sky-500/30 hover:bg-sky-500/20' },
    { value: 'yellow_card', label: 'Amarilla', icon: RectangleVertical, color: 'text-yellow-400', bg: 'bg-yellow-500/10 border-yellow-500/30 hover:bg-yellow-500/20' },
    { value: 'red_card', label: 'Roja', icon: RectangleVertical, color: 'text-red-400', bg: 'bg-red-500/10 border-red-500/30 hover:bg-red-500/20' },
    { value: 'own_goal', label: 'Autogol', icon: Shield, color: 'text-orange-400', bg: 'bg-orange-500/10 border-orange-500/30 hover:bg-orange-500/20' },
    { value: 'penalty_scored', label: 'Penal', icon: CircleDot, color: 'text-emerald-300', bg: 'bg-emerald-500/10 border-emerald-500/30 hover:bg-emerald-500/20' },
    { value: 'penalty_missed', label: 'Penal\nfallado', icon: CircleDot, color: 'text-zinc-400', bg: 'bg-zinc-500/10 border-zinc-500/30 hover:bg-zinc-500/20' },
    { value: 'foul', label: 'Falta', icon: X, color: 'text-amber-400', bg: 'bg-amber-500/10 border-amber-500/30 hover:bg-amber-500/20' },
    { value: 'shot_on_target', label: 'Tiro al\nmarco', icon: Crosshair, color: 'text-teal-400', bg: 'bg-teal-500/10 border-teal-500/30 hover:bg-teal-500/20' },
    { value: 'corner_kick', label: 'Esquina', icon: CornerDownRight, color: 'text-indigo-400', bg: 'bg-indigo-500/10 border-indigo-500/30 hover:bg-indigo-500/20' },
    { value: 'throw_in', label: 'Saque\nbanda', icon: ArrowLeftRight, color: 'text-slate-400', bg: 'bg-slate-500/10 border-slate-500/30 hover:bg-slate-500/20' },
    { value: 'offside', label: 'Fuera de\njuego', icon: Flag, color: 'text-pink-400', bg: 'bg-pink-500/10 border-pink-500/30 hover:bg-pink-500/20' },
    { value: 'substitution', label: 'Cambio', icon: ArrowLeftRight, color: 'text-blue-400', bg: 'bg-blue-500/10 border-blue-500/30 hover:bg-blue-500/20' },
    { value: 'injury', label: 'Lesion', icon: AlertTriangle, color: 'text-rose-400', bg: 'bg-rose-500/10 border-rose-500/30 hover:bg-rose-500/20' },
    { value: 'save', label: 'Atajada', icon: Shield, color: 'text-violet-400', bg: 'bg-violet-500/10 border-violet-500/30 hover:bg-violet-500/20' },
    { value: 'free_kick', label: 'Tiro libre', icon: CircleDot, color: 'text-cyan-400', bg: 'bg-cyan-500/10 border-cyan-500/30 hover:bg-cyan-500/20' },
    { value: 'handball', label: 'Mano', icon: Hand, color: 'text-orange-300', bg: 'bg-orange-500/10 border-orange-500/30 hover:bg-orange-500/20' },
    { value: 'timeout', label: 'Tiempo', icon: Timer, color: 'text-zinc-300', bg: 'bg-zinc-500/10 border-zinc-500/30 hover:bg-zinc-500/20' },
    { value: 'stoppage_start', label: 'Tiempo\ndetenido', icon: Pause, color: 'text-yellow-300', bg: 'bg-yellow-500/10 border-yellow-500/30 hover:bg-yellow-500/20' },
    { value: 'stoppage_end', label: 'Reanudar', icon: Play, color: 'text-emerald-300', bg: 'bg-emerald-500/10 border-emerald-500/30 hover:bg-emerald-500/20' },
    { value: 'water_break', label: 'Hidratacion', icon: Droplets, color: 'text-blue-300', bg: 'bg-blue-500/10 border-blue-500/30 hover:bg-blue-500/20' },
];

const eventPanelColors: Record<string, { border: string; bg: string; bgLight: string; text: string; textLight: string }> = {
    goal: { border: 'border-emerald-500/30', bg: 'bg-emerald-500/10', bgLight: 'bg-emerald-500/5', text: 'text-emerald-400', textLight: 'text-emerald-300/70' },
    assist: { border: 'border-sky-500/30', bg: 'bg-sky-500/10', bgLight: 'bg-sky-500/5', text: 'text-sky-400', textLight: 'text-sky-300/70' },
    yellow_card: { border: 'border-yellow-500/30', bg: 'bg-yellow-500/10', bgLight: 'bg-yellow-500/5', text: 'text-yellow-400', textLight: 'text-yellow-300/70' },
    red_card: { border: 'border-red-500/30', bg: 'bg-red-500/10', bgLight: 'bg-red-500/5', text: 'text-red-400', textLight: 'text-red-300/70' },
    own_goal: { border: 'border-orange-500/30', bg: 'bg-orange-500/10', bgLight: 'bg-orange-500/5', text: 'text-orange-400', textLight: 'text-orange-300/70' },
    penalty_scored: { border: 'border-emerald-500/30', bg: 'bg-emerald-500/10', bgLight: 'bg-emerald-500/5', text: 'text-emerald-300', textLight: 'text-emerald-300/70' },
    penalty_missed: { border: 'border-zinc-500/30', bg: 'bg-zinc-500/10', bgLight: 'bg-zinc-500/5', text: 'text-zinc-400', textLight: 'text-zinc-300/70' },
    foul: { border: 'border-amber-500/30', bg: 'bg-amber-500/10', bgLight: 'bg-amber-500/5', text: 'text-amber-400', textLight: 'text-amber-300/70' },
    shot_on_target: { border: 'border-teal-500/30', bg: 'bg-teal-500/10', bgLight: 'bg-teal-500/5', text: 'text-teal-400', textLight: 'text-teal-300/70' },
    corner_kick: { border: 'border-indigo-500/30', bg: 'bg-indigo-500/10', bgLight: 'bg-indigo-500/5', text: 'text-indigo-400', textLight: 'text-indigo-300/70' },
    throw_in: { border: 'border-slate-500/30', bg: 'bg-slate-500/10', bgLight: 'bg-slate-500/5', text: 'text-slate-400', textLight: 'text-slate-300/70' },
    offside: { border: 'border-pink-500/30', bg: 'bg-pink-500/10', bgLight: 'bg-pink-500/5', text: 'text-pink-400', textLight: 'text-pink-300/70' },
    substitution: { border: 'border-blue-500/30', bg: 'bg-blue-500/10', bgLight: 'bg-blue-500/5', text: 'text-blue-400', textLight: 'text-blue-300/70' },
    injury: { border: 'border-rose-500/30', bg: 'bg-rose-500/10', bgLight: 'bg-rose-500/5', text: 'text-rose-400', textLight: 'text-rose-300/70' },
    save: { border: 'border-violet-500/30', bg: 'bg-violet-500/10', bgLight: 'bg-violet-500/5', text: 'text-violet-400', textLight: 'text-violet-300/70' },
    free_kick: { border: 'border-cyan-500/30', bg: 'bg-cyan-500/10', bgLight: 'bg-cyan-500/5', text: 'text-cyan-400', textLight: 'text-cyan-300/70' },
    handball: { border: 'border-orange-500/30', bg: 'bg-orange-500/10', bgLight: 'bg-orange-500/5', text: 'text-orange-300', textLight: 'text-orange-300/70' },
};
const defaultPanelColor = { border: 'border-amber-500/30', bg: 'bg-amber-500/10', bgLight: 'bg-amber-500/5', text: 'text-amber-400', textLight: 'text-amber-300/70' };

function panelColor() {
    return eventPanelColors[pendingEventType.value ?? ''] ?? defaultPanelColor;
}

// --- Event state ---
const selectedPlayerId = ref<number | null>(null);
const selectedPlayerName = ref('');
const pendingEventType = ref<string | null>(null);
const subOutPlayerId = ref<number | null>(null);
const subOutPlayerName = ref('');
const minute = ref(0);
const second = ref(0);
const submitting = ref(false);

// Video ↔ Events sync (consumer created early so it's ready when a player appears post-encoding)
const videoSync = props.match.video_upload ? useVideoSync(props.match.ulid, 'consumer') : null;
const videoSyncEnabled = ref(!!videoSync);
const timeFrozen = ref(false);

if (videoSync) {
    watch(
        [() => videoSync.syncedMinute.value, () => videoSync.syncedSecond.value],
        ([newMin, newSec]) => {
            if (videoSyncEnabled.value && !timeFrozen.value) {
                minute.value = newMin;
                second.value = newSec;
            }
        },
    );
}
const lastRecorded = ref<{ player: string; event: string; minute: number; second: number } | null>(null);
const deletingEventUlid = ref<string | null>(null);
const deletingEventLabel = ref('');
const showDeleteEventDialog = ref(false);
const recentPlayerIds = ref<number[]>([]);
const isFullscreen = ref(false);
let confirmTimeout: ReturnType<typeof setTimeout> | null = null;

// --- Computed: team players ---
const teamAPlayers = computed(() => props.match.attendances?.filter(a => a.team === 'a') ?? []);
const teamBPlayers = computed(() => props.match.attendances?.filter(a => a.team === 'b') ?? []);
const confirmedPlayers = computed(() => props.match.attendances?.filter(a => a.status === 'confirmed') ?? []);

// --- Fullscreen ---
async function toggleFullscreen() {
    if (!isFullscreen.value) {
        try {
            await document.documentElement.requestFullscreen?.();
            await (screen.orientation as any)?.lock?.('landscape').catch(() => {});
        } catch { /* ignore — iOS doesn't support Fullscreen API */ }
        isFullscreen.value = true;
        nextTick(() => window.scrollTo({ top: 0 }));
    } else {
        try {
            screen.orientation?.unlock?.();
            if (document.fullscreenElement) {
                await document.exitFullscreen?.();
            }
        } catch { /* ignore */ }
        isFullscreen.value = false;
    }
}

function onFullscreenChange() {
    const nativeFullscreen = !!document.fullscreenElement;
    if (!nativeFullscreen && isFullscreen.value) {
        isFullscreen.value = false;
        screen.orientation?.unlock?.();
    }
}

onMounted(() => {
    document.addEventListener('fullscreenchange', onFullscreenChange);
});

onUnmounted(() => {
    if (confirmTimeout) clearTimeout(confirmTimeout);
    document.removeEventListener('fullscreenchange', onFullscreenChange);
});

// --- Unified event flow ---
function onEventSelected(eventType: string) {
    timeFrozen.value = true;
    const scope = eventScopes[eventType] ?? 'player';

    if (scope === 'neutral') {
        recordNeutralEvent(eventType);
        return;
    }

    pendingEventType.value = eventType;
}

function submitTeamOnlyEvent(eventType: string, team: 'a' | 'b') {
    if (submitting.value) return;
    submitting.value = true;

    const teamName = team === 'a' ? props.match.team_a_name : props.match.team_b_name;
    const eventName = EVENT_LABELS[eventType] ?? eventType;
    const recordedMinute = minute.value;
    const recordedSecond = second.value;

    navigator.vibrate?.(50);

    router.post(`${base}/${props.match.ulid}/events`, {
        event_type: eventType,
        team,
        minute: recordedMinute,
        second: recordedSecond,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            lastRecorded.value = { player: teamName, event: eventName, minute: recordedMinute, second: recordedSecond };
            pendingEventType.value = null;
            selectedPlayerId.value = null;
            selectedPlayerName.value = '';
            submitting.value = false;
            if (confirmTimeout) clearTimeout(confirmTimeout);
            confirmTimeout = setTimeout(() => { lastRecorded.value = null; }, 2500);
        },
        onError: () => { submitting.value = false; },
    });
}

function onPlayerSelected(playerId: number, playerName: string) {
    if (pendingEventType.value === 'substitution') {
        if (!subOutPlayerId.value) {
            subOutPlayerId.value = playerId;
            subOutPlayerName.value = playerName;
            return;
        }
        if (playerId === subOutPlayerId.value) return;
        submitSubstitution(subOutPlayerId.value, subOutPlayerName.value, playerId, playerName);
        return;
    }

    if (selectedPlayerId.value === playerId) {
        selectedPlayerId.value = null;
        selectedPlayerName.value = '';
        return;
    }

    selectedPlayerId.value = playerId;
    selectedPlayerName.value = playerName;

    if (pendingEventType.value) {
        submitPlayerEvent(pendingEventType.value, playerId, playerName);
        pendingEventType.value = null;
    }
}

function submitSubstitution(outId: number, outName: string, inId: number, inName: string) {
    if (submitting.value) return;
    submitting.value = true;

    const recordedMinute = minute.value;
    const recordedSecond = second.value;

    addRecentPlayer(inId);
    navigator.vibrate?.(50);

    router.post(`${base}/${props.match.ulid}/events`, {
        player_id: outId,
        related_player_id: inId,
        event_type: 'substitution',
        minute: recordedMinute,
        second: recordedSecond,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            lastRecorded.value = { player: `${outName} → ${inName}`, event: 'Cambio', minute: recordedMinute, second: recordedSecond };
            resetSubstitution();
            submitting.value = false;
            if (confirmTimeout) clearTimeout(confirmTimeout);
            confirmTimeout = setTimeout(() => { lastRecorded.value = null; }, 2500);
        },
        onError: () => { submitting.value = false; },
    });
}

function resetSubstitution() {
    subOutPlayerId.value = null;
    subOutPlayerName.value = '';
    pendingEventType.value = null;
    selectedPlayerId.value = null;
    selectedPlayerName.value = '';
}

function submitPlayerEvent(eventType: string, playerId: number, playerName: string) {
    if (submitting.value) return;
    submitting.value = true;

    const eventName = EVENT_LABELS[eventType] ?? eventType;
    const recordedMinute = minute.value;
    const recordedSecond = second.value;

    addRecentPlayer(playerId);
    navigator.vibrate?.(50);

    router.post(`${base}/${props.match.ulid}/events`, {
        player_id: playerId,
        event_type: eventType,
        minute: recordedMinute,
        second: recordedSecond,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            lastRecorded.value = { player: playerName, event: eventName, minute: recordedMinute, second: recordedSecond };
            selectedPlayerId.value = null;
            selectedPlayerName.value = '';
            pendingEventType.value = null;
            submitting.value = false;
            if (confirmTimeout) clearTimeout(confirmTimeout);
            confirmTimeout = setTimeout(() => { lastRecorded.value = null; }, 2500);
        },
        onError: () => { submitting.value = false; },
    });
}

function recordNeutralEvent(eventType: string) {
    if (submitting.value) return;
    submitting.value = true;

    const eventName = EVENT_LABELS[eventType] ?? eventType;
    const recordedMinute = minute.value;
    const recordedSecond = second.value;

    navigator.vibrate?.(50);

    router.post(`${base}/${props.match.ulid}/events`, {
        event_type: eventType,
        minute: recordedMinute,
        second: recordedSecond,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            lastRecorded.value = { player: 'Partido', event: eventName, minute: recordedMinute, second: recordedSecond };
            submitting.value = false;
            if (confirmTimeout) clearTimeout(confirmTimeout);
            confirmTimeout = setTimeout(() => { lastRecorded.value = null; }, 2500);
        },
        onError: () => { submitting.value = false; },
    });
}

function addRecentPlayer(playerId: number) {
    recentPlayerIds.value = [playerId, ...recentPlayerIds.value.filter(id => id !== playerId)].slice(0, 5);
}

function confirmRemoveEvent(eventUlid: string) {
    const event = props.match.events?.find(e => e.ulid === eventUlid);
    const label = event ? (EVENT_LABELS[event.event_type] ?? event.event_type) : '';
    const player = event?.player?.display_name;
    deletingEventUlid.value = eventUlid;
    deletingEventLabel.value = player ? `${label} — ${player}` : label;
    showDeleteEventDialog.value = true;
}

function executeDeleteEvent() {
    if (!deletingEventUlid.value) return;
    router.delete(`${base}/${props.match.ulid}/events/${deletingEventUlid.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteEventDialog.value = false;
            deletingEventUlid.value = null;
            deletingEventLabel.value = '';
        },
    });
}

// ===== EDIT EVENT DIALOG =====
const editingEvent = ref<MatchEvent | null>(null);
const editForm = useForm({
    event_type: '',
    minute: 0,
    second: 0,
    player_id: null as number | null,
    related_player_id: null as number | null,
    team: null as string | null,
    notes: '',
    highlighted: false,
});

function openEditEvent(event: MatchEvent) {
    editingEvent.value = event;
    editForm.event_type = event.event_type;
    editForm.minute = event.minute;
    editForm.second = event.second;
    editForm.player_id = event.player_id;
    editForm.related_player_id = event.related_player_id;
    editForm.team = event.team ?? props.match.attendances?.find(a => a.player_id === event.player_id)?.team ?? null;
    editForm.notes = event.notes ?? '';
    editForm.highlighted = event.highlighted;
}

const editEventScope = computed(() => eventScopes[editForm.event_type] ?? 'player');

const editTeamPlayers = computed(() => {
    const all = props.match.attendances ?? [];
    return editForm.team ? all.filter(a => a.team === editForm.team) : all;
});

function submitEditEvent() {
    if (!editingEvent.value) return;
    editForm.transform((data) => ({
        ...data,
        player_id: data.player_id || null,
        related_player_id: data.related_player_id || null,
        team: data.team || null,
        notes: data.notes || null,
    })).put(`${base}/${props.match.ulid}/events/${editingEvent.value.ulid}`, {
        preserveScroll: true,
        onSuccess: () => { editingEvent.value = null; },
    });
}

function cancelPendingEvent() {
    pendingEventType.value = null;
    subOutPlayerId.value = null;
    subOutPlayerName.value = '';
    timeFrozen.value = false;
}

// --- Auto-scroll for events tab ---
const actionPanelRef = ref<HTMLElement | null>(null);
const eventGridRef = ref<HTMLElement | null>(null);
const timeBarRef = ref<HTMLElement | null>(null);

function smoothScrollTo(el: HTMLElement | null) {
    if (!el) return;
    const offset = 16;
    const top = el.getBoundingClientRect().top + window.scrollY - offset;
    window.scrollTo({ top, behavior: 'smooth' });
}

function scrollToAction() {
    if (isFullscreen.value) return;
    nextTick(() => smoothScrollTo(timeBarRef.value ?? actionPanelRef.value ?? eventGridRef.value));
}

function scrollToGrid() {
    if (isFullscreen.value) return;
    nextTick(() => smoothScrollTo(timeBarRef.value ?? eventGridRef.value));
}

watch(pendingEventType, (val) => {
    if (val) {
        scrollToAction();
    } else {
        timeFrozen.value = false;
        scrollToGrid();
    }
});

watch(subOutPlayerId, (val) => {
    if (val) scrollToAction();
});

// ===== TAB: JUGADORES — manage players =====
const playerSearchQuery = ref('');
const addingPlayerKey = ref<string | null>(null);
const removingAttendanceUlid = ref<string | null>(null);
const changingTeamUlid = ref<string | null>(null);

const registeredPlayerIds = computed(() =>
    new Set((props.match.attendances ?? []).map(a => a.player_id)),
);

const unregisteredPlayers = computed(() =>
    (props.players ?? []).filter(p => !registeredPlayerIds.value.has(p.id)),
);

const filteredUnregisteredPlayers = computed(() => {
    const q = playerSearchQuery.value.toLowerCase().trim();
    if (!q) return unregisteredPlayers.value;
    return unregisteredPlayers.value.filter(p =>
        p.display_name.toLowerCase().includes(q)
        || p.position_label?.toLowerCase().includes(q)
        || String(p.jersey_number ?? '').includes(q),
    );
});

function addPlayerToMatch(playerId: number, team: 'a' | 'b' | null) {
    addingPlayerKey.value = `${playerId}-${team ?? 'none'}`;
    router.post(`${base}/${props.match.ulid}/attendance`, {
        player_id: playerId,
        status: 'confirmed',
        team,
    }, {
        preserveScroll: true,
        onFinish: () => { addingPlayerKey.value = null; },
    });
}

function removePlayerFromMatch(attendanceUlid: string) {
    removingAttendanceUlid.value = attendanceUlid;
    router.delete(`${base}/${props.match.ulid}/attendance/${attendanceUlid}`, {
        preserveScroll: true,
        onFinish: () => { removingAttendanceUlid.value = null; },
    });
}

function nextTeamCycle(currentTeam: string | null): 'a' | 'b' | null {
    if (currentTeam === 'a') return 'b';
    if (currentTeam === 'b') return null;
    return 'a';
}

function teamLabel(team: string | null): string {
    if (team === 'a') return props.match.team_a_name;
    if (team === 'b') return props.match.team_b_name;
    return 'Sin equipo';
}

function cyclePlayerTeam(attendance: { ulid: string; team: string | null }) {
    const nextTeam = nextTeamCycle(attendance.team);
    changingTeamUlid.value = attendance.ulid;
    router.patch(`${base}/${props.match.ulid}/attendance/${attendance.ulid}`, {
        team: nextTeam,
    }, {
        preserveScroll: true,
        onFinish: () => { changingTeamUlid.value = null; },
    });
}

// --- Create player inline (admin) ---
const showCreatePlayer = ref(false);
const createPlayerForm = useForm({
    name: '',
    position: 'none',
    jersey_number: '',
    _redirect_back: true,
});

function submitCreatePlayer() {
    createPlayerForm.transform((data) => ({
        ...data,
        position: data.position === 'none' ? null : data.position,
        jersey_number: data.jersey_number || null,
    })).post(`/clubs/${props.club.ulid}/players`, {
        preserveScroll: true,
        onSuccess: () => {
            createPlayerForm.reset();
            createPlayerForm.position = 'none';
            showCreatePlayer.value = false;
        },
    });
}

// ===== TAB: REELS =====
const showManualClipForm = ref(false);
const deletingReelUlid = ref<string | null>(null);
const clipTimeInput = ref<InstanceType<typeof MinuteSecondInput> | null>(null);

const videoMaxSeconds = computed(() => props.match.video_upload?.duration_seconds ?? undefined);

const manualClipForm = useForm({
    title: '',
    minute: 0,
    second: 0,
    player_id: 'none',
    request_notes: '',
});

const cachedMediaUrls = new Map<string, string>();

const allReels = computed(() => {
    const reels = props.reels?.data ?? [];
    for (const reel of reels) {
        if (reel.media_url && !cachedMediaUrls.has(reel.ulid)) {
            cachedMediaUrls.set(reel.ulid, reel.media_url);
        }
    }
    return reels;
});

function reelMediaUrl(reel: MatchReel): string | null {
    return cachedMediaUrls.get(reel.ulid) ?? reel.media_url ?? null;
}

const completedReels = computed(() => allReels.value.filter(r => r.status === 'completed' && r.source !== 'request'));
const displayReels = computed(() => allReels.value.filter(r => r.source !== 'request'));

function submitManualClip() {
    manualClipForm.transform((data) => ({
        title: data.title || null,
        minute: data.minute,
        second: data.second,
        player_id: data.player_id === 'none' ? null : data.player_id,
        request_notes: data.request_notes || null,
    })).post(`${base}/${props.match.ulid}/reels`, {
        preserveScroll: true,
        onSuccess: () => {
            manualClipForm.reset();
            showManualClipForm.value = false;
        },
    });
}

const viewBoosts = reactive(new Map<string, number>());

function reelViewCount(reel: MatchReel): number {
    return reel.view_count + (viewBoosts.get(reel.ulid) ?? 0);
}

function trackReelView(reel: MatchReel) {
    if (viewBoosts.has(reel.ulid)) return;
    viewBoosts.set(reel.ulid, 1);

    fetch(`${base}/${props.match.ulid}/reels/${reel.ulid}/view`, {
        method: 'POST',
        headers: { 'X-XSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
    });
}

const refreshingReels = ref(false);
const confirmDeleteReelUlid = ref<string | null>(null);

function refreshReels() {
    refreshingReels.value = true;
    router.reload({
        only: ['reels'],
        reset: ['reels'],
        onFinish: () => { refreshingReels.value = false; },
    });
}

function onDeleteReelClick(reel: MatchReel) {
    if (reel.status === 'failed' || reel.status === 'pending') {
        deleteReel(reel);
    } else {
        confirmDeleteReelUlid.value = reel.ulid;
    }
}

const generatingReels = ref(false);
const hasProcessingReels = computed(() => allReels.value.some(r => r.status === 'processing'));

function generateAutoReels() {
    generatingReels.value = true;
    router.post(`${base}/${props.match.ulid}/reels/generate`, {}, {
        preserveScroll: true,
        onFinish: () => { generatingReels.value = false; },
    });
}

function deleteReel(reel: MatchReel) {
    deletingReelUlid.value = reel.ulid;
    router.delete(`${base}/${props.match.ulid}/reels/${reel.ulid}`, {
        preserveScroll: true,
        onFinish: () => {
            deletingReelUlid.value = null;
            confirmDeleteReelUlid.value = null;
        },
    });
}

function toggleHighlighted(event: MatchEvent) {
    router.patch(`${base}/${props.match.ulid}/events/${event.ulid}`, {
        highlighted: !event.highlighted,
    }, { preserveScroll: true });
}

function formatSeconds(totalSeconds: number): string {
    const m = Math.floor(totalSeconds / 60);
    const s = totalSeconds % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
}

function sourceBadgeClass(source: string): string {
    if (source === 'auto') return 'bg-blue-500/10 text-blue-400 border-blue-500/30';
    if (source === 'manual') return 'bg-purple-500/10 text-purple-400 border-purple-500/30';
    return 'bg-amber-500/10 text-amber-400 border-amber-500/30';
}

function sourceLabel(source: string): string {
    if (source === 'auto') return 'Auto';
    if (source === 'manual') return 'Manual';
    return 'Solicitud';
}

async function downloadReel(reel: MatchReel) {
    if (!reel.media_url) return;
    const response = await fetch(reel.media_url);
    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${reel.title.replace(/[^a-zA-Z0-9áéíóúñÁÉÍÓÚÑ ]/g, '_')}.mp4`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

async function shareReel(reel: MatchReel) {
    if (!reel.media_url) return;

    const shareData: ShareData = {
        title: reel.title,
        text: `${reel.title} — ${props.match.title}`,
    };

    // Try sharing the actual file on mobile
    if (navigator.canShare) {
        try {
            const response = await fetch(reel.media_url);
            const blob = await response.blob();
            const file = new File([blob], `${reel.title.replace(/[^a-zA-Z0-9]/g, '_')}.mp4`, { type: 'video/mp4' });
            const fileShareData = { ...shareData, files: [file] };

            if (navigator.canShare(fileShareData)) {
                await navigator.share(fileShareData);
                return;
            }
        } catch {
            // Fall through to URL share
        }
    }

    // Fallback: share URL or copy
    if (navigator.share) {
        try {
            await navigator.share({ ...shareData, url: reel.media_url });
            return;
        } catch {
            // User cancelled or error
        }
    }

    // Final fallback: copy to clipboard
    await navigator.clipboard.writeText(reel.media_url);
    alert('Enlace copiado al portapapeles');
}
</script>

<template>
    <Head title="Resumen del Partido" />
    <component :is="isFullscreen ? 'div' : AppLayout" :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full px-3 py-3 sm:px-4 sm:py-6" :class="isFullscreen ? 'max-w-5xl' : 'max-w-2xl'">

            <!-- ===== SCOREBOARD HERO ===== -->
            <div v-if="!isFullscreen" class="relative overflow-hidden rounded-2xl bg-gradient-to-b from-emerald-950 via-emerald-950/90 to-zinc-950 p-6 shadow-lg">
                <!-- Pitch decoration -->
                <div class="pointer-events-none absolute inset-0 opacity-[0.05]">
                    <div class="absolute top-1/2 left-1/2 size-32 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white"></div>
                    <div class="absolute inset-y-0 left-1/2 w-px bg-white"></div>
                </div>

                <div class="relative text-center">
                    <!-- Status + Edit -->
                    <div class="flex items-center justify-between">
                        <span class="inline-block rounded-full border border-blue-500/30 bg-blue-500/20 px-3 py-0.5 text-[10px] font-bold tracking-widest text-blue-300 uppercase">
                            FINALIZADO
                        </span>
                        <Link v-if="isAdmin" :href="`${base}/${match.ulid}/edit`">
                            <Button variant="ghost" size="icon" class="size-8 text-zinc-400 hover:text-white">
                                <Pencil class="size-4" />
                            </Button>
                        </Link>
                    </div>

                    <p class="mt-2 text-sm font-medium text-zinc-400">{{ match.title }}</p>

                    <!-- Score with team colors -->
                    <div class="mt-4 flex items-center justify-center gap-4 sm:gap-8">
                        <div class="min-w-0 flex-1 text-right">
                            <div class="mb-2 flex items-center justify-end gap-2">
                                <p class="truncate text-xs font-bold tracking-wider text-zinc-400 uppercase sm:text-sm">{{ match.team_a_name }}</p>
                                <span class="size-3 shrink-0 rounded-sm" :style="{ backgroundColor: match.team_a_color ?? undefined }"></span>
                            </div>
                            <input
                                v-if="editingScore"
                                v-model.number="scoreForm.team_a_score"
                                type="number"
                                min="0"
                                max="99"
                                class="w-20 border-b-2 border-zinc-600 bg-transparent text-center text-5xl font-black tabular-nums text-white focus:border-primary focus:outline-none sm:text-6xl ml-auto block"
                            />
                            <p v-else class="text-5xl font-black tabular-nums text-white sm:text-6xl">{{ teamAGoals }}</p>
                        </div>

                        <div class="flex flex-col items-center">
                            <span class="text-xl font-light text-zinc-600 select-none">vs</span>
                        </div>

                        <div class="min-w-0 flex-1 text-left">
                            <div class="mb-2 flex items-center gap-2">
                                <span class="size-3 shrink-0 rounded-sm" :style="{ backgroundColor: match.team_b_color ?? undefined }"></span>
                                <p class="truncate text-xs font-bold tracking-wider text-zinc-400 uppercase sm:text-sm">{{ match.team_b_name }}</p>
                            </div>
                            <input
                                v-if="editingScore"
                                v-model.number="scoreForm.team_b_score"
                                type="number"
                                min="0"
                                max="99"
                                class="w-20 border-b-2 border-zinc-600 bg-transparent text-center text-5xl font-black tabular-nums text-white focus:border-primary focus:outline-none sm:text-6xl"
                            />
                            <p v-else class="text-5xl font-black tabular-nums text-white sm:text-6xl">{{ teamBGoals }}</p>
                        </div>
                    </div>

                    <!-- Score edit controls (admin only) -->
                    <div v-if="isAdmin" class="mt-3 flex justify-center">
                        <template v-if="editingScore">
                            <div class="flex gap-2">
                                <Button size="sm" variant="ghost" class="text-zinc-400 hover:text-white" @click="editingScore = false">
                                    <X class="mr-1 size-3.5" />
                                    Cancelar
                                </Button>
                                <Button size="sm" :disabled="scoreForm.processing" @click="saveScore">
                                    <Check class="mr-1 size-3.5" />
                                    Guardar
                                </Button>
                            </div>
                        </template>
                        <Button v-else size="sm" :variant="match.team_a_score !== null ? 'ghost' : 'outline'" :class="match.team_a_score !== null ? 'text-zinc-400 hover:text-zinc-200' : 'border-zinc-600 text-zinc-200 hover:bg-zinc-800 hover:text-white'" @click="startEditingScore">
                            <Pencil class="mr-1.5 size-3.5" />
                            {{ match.team_a_score !== null ? 'Editar marcador' : 'Agregar marcador' }}
                        </Button>
                    </div>

                    <!-- Top scorer highlight -->
                    <div v-if="topScorer" class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-amber-500/10 px-3 py-1 text-sm text-amber-400">
                        <Trophy class="size-3.5" />
                        <span class="font-semibold">{{ topScorer.name }}</span>
                        <span class="text-amber-500/60">&mdash; {{ topScorer.goals }} {{ topScorer.goals === 1 ? 'gol' : 'goles' }}</span>
                    </div>
                </div>
            </div>

            <!-- Compact scoreboard in fullscreen -->
            <div v-else class="sticky top-0 z-20 -mx-3 bg-background/95 px-3 pb-2 backdrop-blur-sm">
                <LiveScoreboard
                    :match="match"
                    clock-display=""
                    :team-a-goals="teamAGoals"
                    :team-b-goals="teamBGoals"
                />
                <div class="mt-2 flex justify-center">
                    <Button variant="outline" size="sm" class="min-h-[44px]" @click="toggleFullscreen">
                        <Minimize class="mr-1.5 size-3.5" />
                        Salir de pantalla completa
                    </Button>
                </div>
            </div>

            <!-- ===== VIDEO DEL PARTIDO ===== -->
            <div v-if="hasVideoAvailable && (youtubeVideoId || videoStreamUrl || videoEmbedUrl) && !isFullscreen" class="mt-4">
                <!-- YouTube player with advanced controls (admin sync) -->
                <YouTubePlayer v-if="youtubeVideoId && isAdmin" :video-id="youtubeVideoId" :match-ulid="match.ulid" />
                <!-- YouTube embed for non-admins -->
                <div v-else-if="youtubeVideoId" class="aspect-video w-full overflow-hidden rounded-xl border border-border">
                    <iframe :src="`https://www.youtube.com/embed/${youtubeVideoId}`" class="h-full w-full" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen />
                </div>

                <!-- Drive HTML5 player with sync (admin only, post-encoding) -->
                <DrivePlayer v-else-if="videoStreamUrl && isAdmin" :stream-url="videoStreamUrl" :match-ulid="match.ulid" />
                <!-- Encoding message for admins (no player until video is optimized) -->
                <div v-else-if="isAdmin && hasVideoEncoding" class="rounded-xl border border-amber-500/30 bg-amber-500/5 p-4">
                    <div class="flex items-center gap-3">
                        <Loader2 class="size-5 shrink-0 animate-spin text-amber-400" />
                        <div>
                            <p class="text-sm font-medium text-amber-400">Optimizando video...</p>
                            <p class="text-xs text-muted-foreground">Estamos generando una version optimizada del video para cargar estadisticas y generar reels. Esto puede tomar varios minutos.</p>
                        </div>
                    </div>
                </div>
                <!-- Drive embed for non-admins -->
                <div v-else-if="videoEmbedUrl" class="aspect-video w-full overflow-hidden rounded-xl border border-border">
                    <iframe :src="videoEmbedUrl" class="h-full w-full" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen />
                </div>

                <!-- Hint when using Drive embed (Drive may still be processing) -->
                <div v-if="!youtubeVideoId && videoEmbedUrl && !isAdmin" class="mt-1.5 text-xs text-muted-foreground">
                    Si el video no se reproduce, espera unos minutos mientras se termina de procesar.
                </div>
                <div v-if="isAdmin && hasVideoReady && !hasYouTube" class="mt-1.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                    Pendiente de subir a YouTube
                </div>
                <div class="mt-2 flex items-center justify-between">
                    <!-- Copy video link (YouTube or Drive) -->
                    <div v-if="youtubeUrl || driveViewUrl" class="flex items-center gap-2">
                        <Button type="button" variant="outline" size="sm" class="gap-1.5" @click="copyVideoLink">
                            <Copy class="size-3.5" />
                            {{ copiedLink ? 'Copiado!' : 'Copiar link' }}
                        </Button>
                        <a v-if="youtubeUrl" :href="youtubeUrl" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-xs text-muted-foreground transition-colors hover:text-foreground">
                            <ExternalLink class="size-3" />
                            YouTube
                        </a>
                        <a v-else-if="driveViewUrl" :href="driveViewUrl" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-xs text-muted-foreground transition-colors hover:text-foreground">
                            <ExternalLink class="size-3" />
                            Google Drive
                        </a>
                    </div>
                    <div v-else></div>
                    <!-- Delete video (admin) -->
                    <Dialog v-if="isAdmin" v-model:open="showDeleteVideoDialog">
                        <DialogTrigger as-child>
                            <Button type="button" variant="ghost" size="sm" class="gap-1.5 text-destructive hover:text-destructive">
                                <Trash2 class="size-3.5" />
                                Eliminar video
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Eliminar video del partido</DialogTitle>
                                <DialogDescription>
                                    Se eliminara el video de este partido. Los reels generados se mantendran pero no se podran generar nuevos hasta subir otro video.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter class="flex flex-col gap-2 sm:flex-row">
                                <DialogClose as-child>
                                    <Button variant="outline">Cancelar</Button>
                                </DialogClose>
                                <Button variant="destructive" class="gap-2" :disabled="deletingVideo" @click="confirmDeleteVideo">
                                    <Trash2 class="size-4" />
                                    {{ deletingVideo ? 'Eliminando...' : 'Eliminar video' }}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
            <div v-else-if="hasVideoReady && !isFullscreen" class="mt-4">
                <VideoPlayer v-if="s3VideoUrl" :src="s3VideoUrl" />
                <div class="mt-2 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Button v-if="isAdmin" type="button" variant="outline" size="sm" class="gap-1.5" :disabled="generatingShareLink" @click="generateShareLink">
                            <Copy class="size-3.5" />
                            {{ copiedShareLink ? 'Link copiado!' : 'Copiar link' }}
                        </Button>
                        <Button v-if="match.share_token" type="button" variant="outline" size="sm" class="gap-1.5" @click="copyPublicLink">
                            <Copy class="size-3.5" />
                            {{ copiedPublicLink ? 'Link copiado!' : 'Página pública' }}
                        </Button>
                    </div>
                    <Dialog v-if="isAdmin" v-model:open="showDeleteVideoDialog">
                        <DialogTrigger as-child>
                            <Button type="button" variant="ghost" size="sm" class="gap-1.5 text-destructive hover:text-destructive">
                                <Trash2 class="size-3.5" />
                                Eliminar
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Eliminar video del partido</DialogTitle>
                                <DialogDescription>
                                    Se eliminara el video de este partido. Los reels generados se mantendran pero no se podran generar nuevos hasta subir otro video.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter class="flex flex-col gap-2 sm:flex-row">
                                <DialogClose as-child>
                                    <Button variant="outline">Cancelar</Button>
                                </DialogClose>
                                <Button variant="destructive" class="gap-2" :disabled="deletingVideo" @click="confirmDeleteVideo">
                                    <Trash2 class="size-4" />
                                    {{ deletingVideo ? 'Eliminando...' : 'Eliminar video' }}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
            <div v-else-if="isAdmin && !hasVideoReady && !isFullscreen" class="mt-4">
                <VideoUploader
                    :club-ulid="club.ulid"
                    :match-ulid="match.ulid"
                    :existing-upload="match.video_upload"
                    :s3-video-url="s3VideoUrl"
                    @uploaded="() => router.reload()"
                />
            </div>
            <div v-else-if="match.video_upload?.status === 'encoding' && !isFullscreen" class="mt-4 rounded-xl border border-amber-500/30 bg-amber-500/5 p-4">
                <div class="flex items-center justify-center gap-2">
                    <Loader2 class="size-4 animate-spin text-amber-400" />
                    <p class="text-xs text-amber-400">Procesando video...</p>
                </div>
                <div class="mt-2 flex justify-center">
                    <Button variant="ghost" size="sm" class="gap-1.5 text-xs" @click="() => router.reload()">
                        <RefreshCw class="size-3" />
                        Actualizar estado
                    </Button>
                </div>
            </div>
            <div v-else-if="match.video_upload?.status === 'failed' && !isFullscreen" class="mt-4 rounded-xl border border-red-500/30 bg-red-500/5 p-4">
                <div class="flex items-center justify-center gap-2">
                    <AlertTriangle class="size-4 text-red-400" />
                    <p class="text-xs text-red-400">{{ match.video_upload.error_message || 'Error al procesar el video' }}</p>
                </div>
                <div class="mt-2 flex justify-center">
                    <Button variant="ghost" size="sm" class="gap-1.5 text-xs" @click="() => router.reload()">
                        <RefreshCw class="size-3" />
                        Verificar estado
                    </Button>
                </div>
            </div>
            <div v-else-if="!hasVideoReady && !isFullscreen" class="mt-4 rounded-xl border border-dashed border-border p-4 text-center">
                <Video class="mx-auto mb-1 size-6 text-muted-foreground" />
                <p class="text-xs text-muted-foreground">Sin video del partido</p>
            </div>

            <!-- ===== MATCH INFO (compact) ===== -->
            <div v-if="!isFullscreen" class="mt-3 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                <span class="inline-flex items-center gap-1">
                    <Calendar class="size-3" />
                    {{ formattedDate }}, {{ formattedTime }}
                </span>
                <span class="inline-flex items-center gap-1">
                    <Clock class="size-3" />
                    {{ matchDuration }}'
                </span>
                <span v-if="match.field" class="inline-flex items-center gap-1">
                    <MapPin class="size-3" />
                    {{ match.field.name }}<template v-if="match.field.venue"> · {{ match.field.venue.name }}</template>
                </span>
            </div>

            <!-- Stats finalized badge -->
            <div v-if="match.stats_finalized_at && !isFullscreen" class="mt-2 text-center">
                <Badge variant="secondary" class="gap-1">
                    <Star class="size-3" />
                    Estadisticas acumuladas el {{ formatStatsDate(match.stats_finalized_at) }}
                </Badge>
            </div>

            <!-- CTA: Register stats (visible for admin when not yet finalized) -->
            <div v-if="isAdmin && !match.stats_finalized_at && !isFullscreen" class="mt-3 rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-4 text-center">
                <Star class="mx-auto mb-2 size-6 text-emerald-500" />
                <p class="text-sm font-medium">¿Listo para cerrar este partido?</p>
                <p class="mt-1 text-xs text-muted-foreground">Registra las estadísticas en el perfil de cada jugador y envíales su resumen.</p>
                <Dialog v-model:open="showFinalizeDialog">
                    <DialogTrigger as-child>
                        <Button class="mt-3 w-full gap-2">
                            <Star class="size-4" />
                            Registrar y notificar
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>¿Ya registraste todos los eventos?</DialogTitle>
                            <DialogDescription>
                                Al confirmar, las estadísticas de este partido se acumularán en el perfil de cada jugador y se les enviará una notificación con su resumen. Si aún te faltan goles, tarjetas u otros eventos por registrar, puedes seguir editando y volver cuando estés listo.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter class="flex flex-col gap-2 sm:flex-row">
                            <DialogClose as-child>
                                <Button variant="outline" class="w-full sm:w-auto">Seguir editando</Button>
                            </DialogClose>
                            <Button class="w-full gap-2 sm:w-auto" @click="showFinalizeDialog = false; finalizeStats();">
                                <Star class="size-4" />
                                Registrar y notificar
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>

            <!-- ===== TAB BAR (non-admin, when reels exist) ===== -->
            <div v-if="!isAdmin && completedReels.length && !isFullscreen" class="mt-4 flex rounded-xl border border-border bg-card p-1">
                <button
                    class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-2.5 text-xs font-semibold transition-colors"
                    :class="activeTab === 'resumen' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:bg-accent'"
                    @click="activeTab = 'resumen'"
                >
                    <Trophy class="size-3.5" />
                    Resumen
                </button>
                <button
                    class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-2.5 text-xs font-semibold transition-colors"
                    :class="activeTab === 'reels'
                        ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-500/30'
                        : 'bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20'"
                    @click="activeTab = 'reels'"
                >
                    <Film class="size-3.5" />
                    Lo mejor
                    <span class="rounded-full bg-emerald-500/20 px-1.5 py-0.5 text-[10px] font-bold" :class="activeTab === 'reels' ? 'bg-white/20 text-white' : 'text-emerald-400'">{{ completedReels.length }}</span>
                </button>
            </div>

            <!-- ===== TAB BAR (admin) ===== -->
            <div v-if="isAdmin && !isFullscreen" class="mt-4 flex rounded-xl border border-border bg-card p-1">
                <button
                    class="flex flex-1 items-center justify-center gap-1 rounded-lg px-2 py-2.5 text-[11px] font-semibold transition-colors"
                    :class="activeTab === 'resumen' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:bg-accent'"
                    @click="activeTab = 'resumen'"
                >
                    <Trophy class="size-3.5 shrink-0" />
                    Resumen
                </button>
                <button
                    class="flex flex-1 items-center justify-center gap-1 rounded-lg px-2 py-2.5 text-[11px] font-semibold transition-colors"
                    :class="activeTab === 'eventos' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:bg-accent'"
                    @click="activeTab = 'eventos'"
                >
                    <Pencil class="size-3.5 shrink-0" />
                    Eventos
                </button>
                <button
                    class="flex flex-1 items-center justify-center gap-1 rounded-lg px-2 py-2.5 text-[11px] font-semibold transition-colors"
                    :class="activeTab === 'jugadores' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:bg-accent'"
                    @click="activeTab = 'jugadores'"
                >
                    <Users class="size-3.5 shrink-0" />
                    Jugadores
                </button>
                <button
                    class="flex flex-1 items-center justify-center gap-1 rounded-lg px-2 py-2.5 text-[11px] font-semibold transition-colors"
                    :class="activeTab === 'reels' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:bg-accent'"
                    @click="activeTab = 'reels'"
                >
                    <Film class="size-3.5 shrink-0" />
                    Lo mejor
                </button>
            </div>

            <!-- ========================================== -->
            <!-- TAB: RESUMEN (or default for non-admin)    -->
            <!-- ========================================== -->
            <div v-if="activeTab === 'resumen'" v-show="!isFullscreen">
                <!-- ===== TIMELINE ===== -->
                <div v-if="sortedEvents.length" class="mt-6">
                    <h3 class="mb-4 text-xs font-extrabold tracking-widest text-muted-foreground uppercase">
                        Timeline
                        <span class="ml-1 font-normal opacity-60">({{ sortedEvents.length }})</span>
                    </h3>

                    <div class="relative space-y-0">
                        <!-- Center line -->
                        <div class="absolute inset-y-0 left-1/2 w-px -translate-x-1/2 bg-border"></div>

                        <div
                            v-for="event in sortedEvents"
                            :key="event.id"
                            class="relative flex items-center gap-2 py-1.5"
                            :class="getEventTeam(event) === 'b' ? 'flex-row-reverse' : ''"
                        >
                            <!-- Event card -->
                            <div
                                class="flex min-w-0 flex-1 items-center gap-2.5 rounded-lg border border-border px-3 py-2.5"
                                :class="getEventTeam(event) === 'b' ? 'flex-row-reverse text-right' : ''"
                                :style="{
                                    borderLeftWidth: getEventTeam(event) === 'a' ? '3px' : undefined,
                                    borderRightWidth: getEventTeam(event) === 'b' ? '3px' : undefined,
                                    borderLeftColor: getEventTeam(event) === 'a' ? (teamColor('a')) : undefined,
                                    borderRightColor: getEventTeam(event) === 'b' ? (teamColor('b')) : undefined,
                                }"
                            >
                                <!-- Icon -->
                                <div class="shrink-0">
                                    <EventIcon :event-type="event.event_type" />
                                </div>

                                <!-- Content -->
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold" :class="EVENT_ICON_COLORS[event.event_type] ?? 'text-foreground'">
                                        {{ EVENT_LABELS[event.event_type] ?? event.event_type }}
                                    </p>
                                    <div class="flex items-center gap-1.5 text-xs" :class="getEventTeam(event) === 'b' ? 'flex-row-reverse' : ''">
                                        <span
                                            v-if="getEventTeam(event)"
                                            class="size-2 shrink-0 rounded-full"
                                            :style="{ backgroundColor: teamColor(getEventTeam(event)) }"
                                        ></span>
                                        <span class="truncate text-foreground/70">
                                            <template v-if="event.player">
                                                <Link
                                                    :href="`/clubs/${club.ulid}/players/${event.player.ulid}`"
                                                    class="hover:text-primary hover:underline"
                                                >{{ event.player.display_name }}</Link>
                                                <template v-if="event.event_type === 'substitution' && event.related_player">
                                                    <span class="text-blue-400"> &rarr; </span>
                                                    <Link
                                                        :href="`/clubs/${club.ulid}/players/${event.related_player.ulid}`"
                                                        class="hover:text-primary hover:underline"
                                                    >{{ event.related_player.display_name }}</Link>
                                                </template>
                                            </template>
                                            <template v-else-if="getEventTeam(event)">
                                                {{ getEventTeam(event) === 'a' ? match.team_a_name : match.team_b_name }}
                                            </template>
                                        </span>
                                    </div>
                                </div>

                                <!-- Highlight toggle (admin, player-scoped events) -->
                                <button
                                    v-if="isAdmin && event.player_id"
                                    class="shrink-0 p-1 transition-colors"
                                    :class="event.highlighted ? 'text-amber-400' : 'text-zinc-600 hover:text-amber-400/60'"
                                    :title="event.highlighted ? 'Quitar destacado' : 'Marcar como destacado'"
                                    @click.prevent="toggleHighlighted(event)"
                                >
                                    <Star class="size-3.5" :class="event.highlighted ? 'fill-amber-400' : ''" />
                                </button>
                            </div>

                            <!-- Minute bubble (center) -->
                            <div class="z-10 flex shrink-0 flex-col items-center">
                                <span class="flex size-10 items-center justify-center rounded-full border border-border bg-card text-xs font-bold tabular-nums">
                                    {{ event.minute }}<span class="text-[8px] text-muted-foreground">'</span>
                                </span>
                                <span v-if="event.second > 0" class="mt-0.5 text-[8px] tabular-nums text-muted-foreground">
                                    {{ String(event.second).padStart(2, '0') }}s
                                </span>
                            </div>

                            <!-- Spacer for the other side -->
                            <div class="flex-1"></div>
                        </div>
                    </div>
                </div>

                <div v-else class="mt-6 text-center text-sm text-muted-foreground">
                    No se registraron eventos en este partido.
                </div>

                <!-- ===== PLAYER STATS BY TEAM ===== -->
                <div v-if="playerStats.size" class="mt-6 space-y-4">
                    <h3 class="text-[10px] font-bold tracking-widest text-muted-foreground uppercase">
                        Estadisticas por jugador
                    </h3>

                    <!-- Team A stats -->
                    <div v-if="teamAStats.length" class="overflow-hidden rounded-xl border border-border">
                        <div class="flex items-center gap-2.5 px-4 py-2.5" :style="{ backgroundColor: (match.team_a_color ?? '') + '20' }">
                            <span class="size-4 shrink-0 rounded-sm" :style="{ backgroundColor: match.team_a_color ?? undefined }"></span>
                            <span class="flex-1 text-sm font-bold">{{ match.team_a_name }}</span>
                        </div>
                        <div class="divide-y divide-border/50">
                            <div
                                v-for="stat in teamAStats"
                                :key="stat.id"
                                class="flex items-center gap-3 bg-card px-4 py-2.5"
                            >
                                <div
                                    class="flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                    :style="{ backgroundColor: (match.team_a_color ?? '') + '30' }"
                                >
                                    {{ stat.jerseyNumber ?? stat.name.charAt(0).toUpperCase() }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <Link :href="`/clubs/${club.ulid}/players/${stat.ulid}`" class="block truncate text-sm font-medium hover:text-primary hover:underline">{{ stat.name }}</Link>
                                    <div class="flex flex-wrap gap-3 text-xs text-muted-foreground">
                                        <span v-if="stat.goals" class="flex items-center gap-1">
                                            <CircleDot class="size-3 text-emerald-400" /> {{ stat.goals }} {{ stat.goals === 1 ? 'gol' : 'goles' }}
                                        </span>
                                        <span v-if="stat.assists">{{ stat.assists }} {{ stat.assists === 1 ? 'asist.' : 'asist.' }}</span>
                                        <span v-if="stat.yellowCards" class="flex items-center gap-1">
                                            <RectangleVertical class="size-3 text-yellow-400" /> {{ stat.yellowCards }}
                                        </span>
                                        <span v-if="stat.redCards" class="flex items-center gap-1">
                                            <RectangleVertical class="size-3 text-red-400" /> {{ stat.redCards }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Team B stats -->
                    <div v-if="teamBStats.length" class="overflow-hidden rounded-xl border border-border">
                        <div class="flex items-center gap-2.5 px-4 py-2.5" :style="{ backgroundColor: (match.team_b_color ?? '') + '20' }">
                            <span class="size-4 shrink-0 rounded-sm" :style="{ backgroundColor: match.team_b_color ?? undefined }"></span>
                            <span class="flex-1 text-sm font-bold">{{ match.team_b_name }}</span>
                        </div>
                        <div class="divide-y divide-border/50">
                            <div
                                v-for="stat in teamBStats"
                                :key="stat.id"
                                class="flex items-center gap-3 bg-card px-4 py-2.5"
                            >
                                <div
                                    class="flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                    :style="{ backgroundColor: (match.team_b_color ?? '') + '30' }"
                                >
                                    {{ stat.jerseyNumber ?? stat.name.charAt(0).toUpperCase() }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <Link :href="`/clubs/${club.ulid}/players/${stat.ulid}`" class="block truncate text-sm font-medium hover:text-primary hover:underline">{{ stat.name }}</Link>
                                    <div class="flex flex-wrap gap-3 text-xs text-muted-foreground">
                                        <span v-if="stat.goals" class="flex items-center gap-1">
                                            <CircleDot class="size-3 text-emerald-400" /> {{ stat.goals }} {{ stat.goals === 1 ? 'gol' : 'goles' }}
                                        </span>
                                        <span v-if="stat.assists">{{ stat.assists }} {{ stat.assists === 1 ? 'asist.' : 'asist.' }}</span>
                                        <span v-if="stat.yellowCards" class="flex items-center gap-1">
                                            <RectangleVertical class="size-3 text-yellow-400" /> {{ stat.yellowCards }}
                                        </span>
                                        <span v-if="stat.redCards" class="flex items-center gap-1">
                                            <RectangleVertical class="size-3 text-red-400" /> {{ stat.redCards }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== ADMIN ACTIONS (in Resumen tab) ===== -->
                <div v-if="isAdmin" class="mt-6 flex justify-end">
                    <Dialog v-model:open="showDeleteDialog">
                        <DialogTrigger as-child>
                            <Button type="button" variant="ghost" size="sm" class="gap-1.5 text-destructive hover:text-destructive">
                                <Trash2 class="size-3.5" />
                                Eliminar partido
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Eliminar partido</DialogTitle>
                                <DialogDescription>
                                    Esta acción no se puede deshacer. Se eliminará el partido
                                    <strong>"{{ match.title }}"</strong> junto con toda su información
                                    de asistencia y eventos.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter class="flex flex-col gap-2 sm:flex-row">
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

            </div>

            <!-- ========================================== -->
            <!-- TAB: EVENTOS (admin only, v-show to keep state) -->
            <!-- ========================================== -->
            <div v-if="isAdmin" v-show="activeTab === 'eventos' || isFullscreen">
                <div class="mt-4 space-y-4">
                    <!-- Fullscreen toggle -->
                    <div v-if="!isFullscreen" class="flex justify-end">
                        <Button variant="outline" size="sm" class="gap-1.5" @click="toggleFullscreen">
                            <Maximize class="size-3.5" />
                            Pantalla completa
                        </Button>
                    </div>

                    <!-- Event type grid + MinuteSecondInput (sticky) -->
                    <div>
                        <div ref="timeBarRef" class="sticky top-0 z-10 mb-2 flex items-center justify-between rounded-lg bg-background/95 py-2 backdrop-blur-sm">
                            <div class="flex items-center gap-2">
                                <h3 v-if="!pendingEventType" class="text-xs font-extrabold tracking-widest text-muted-foreground uppercase">
                                    Registrar evento
                                </h3>
                                <button
                                    v-if="videoSync && (youtubeVideoId || videoStreamUrl)"
                                    class="flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium transition-colors"
                                    :class="videoSyncEnabled
                                        ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30'
                                        : 'bg-zinc-500/10 text-zinc-400 border border-zinc-500/30'"
                                    @click="videoSyncEnabled = !videoSyncEnabled"
                                >
                                    <span
                                        class="size-1.5 rounded-full"
                                        :class="videoSyncEnabled && videoSync.isPlaying.value ? 'bg-emerald-400 animate-pulse' : 'bg-zinc-400'"
                                    />
                                    {{ videoSyncEnabled ? (timeFrozen ? 'Capturado' : 'Sincronizado') : 'Manual' }}
                                </button>
                            </div>
                            <MinuteSecondInput
                                v-model:minute="minute"
                                v-model:second="second"
                                :manual-mode="false"
                                always-expanded
                            />
                        </div>

                        <!-- ===== PENDING EVENT: integrated panel ===== -->
                        <div v-if="pendingEventType" ref="actionPanelRef" class="mb-2">
                            <!-- Substitution flow -->
                            <div v-if="pendingEventType === 'substitution'" class="overflow-hidden rounded-xl border border-blue-500/30">
                                <div class="flex items-center justify-between bg-blue-500/10 px-4 py-3">
                                    <div>
                                        <p class="text-sm font-bold text-blue-400">Cambio</p>
                                        <p v-if="!subOutPlayerId" class="text-xs text-blue-300/70">Toca el jugador que SALE</p>
                                        <p v-else class="text-xs text-blue-300/70"><strong>{{ subOutPlayerName }}</strong> sale &mdash; toca quien ENTRA</p>
                                    </div>
                                    <button
                                        class="rounded-lg border border-blue-500/30 px-3 py-1.5 text-xs font-medium text-blue-400 transition-colors hover:bg-blue-500/20"
                                        @click="cancelPendingEvent"
                                    >
                                        Cancelar
                                    </button>
                                </div>
                                <div class="bg-blue-500/5 p-3">
                                    <PlayerSelector
                                        :team-a-players="teamAPlayers"
                                        :team-b-players="teamBPlayers"
                                        :team-a-name="match.team_a_name"
                                        :team-b-name="match.team_b_name"
                                        :team-a-color="match.team_a_color"
                                        :team-b-color="match.team_b_color"
                                        :selected-player-id="subOutPlayerId"
                                        :disabled-player-id="subOutPlayerId"
                                        :recent-player-ids="recentPlayerIds"
                                        hide-headers
                                        @select="onPlayerSelected"
                                    />
                                </div>
                            </div>

                            <!-- Normal event: integrated panel -->
                            <div v-else class="overflow-hidden rounded-xl border" :class="panelColor().border">
                                <div class="flex items-center justify-between px-4 py-3" :class="panelColor().bg">
                                    <div>
                                        <p class="text-sm font-bold" :class="panelColor().text">{{ EVENT_LABELS[pendingEventType] }}</p>
                                        <p class="text-xs" :class="panelColor().textLight">
                                            <template v-if="eventScopes[pendingEventType] === 'player'">Toca un jugador o asigna al equipo</template>
                                            <template v-else>Selecciona el equipo</template>
                                        </p>
                                    </div>
                                    <button
                                        class="rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors"
                                        :class="[panelColor().border, panelColor().text]"
                                        @click="cancelPendingEvent"
                                    >
                                        Cancelar
                                    </button>
                                </div>
                                <div class="p-3 space-y-3" :class="panelColor().bgLight">
                                    <!-- Player selector (for player-scope events) -->
                                    <template v-if="eventScopes[pendingEventType] === 'player'">
                                        <PlayerSelector
                                            :team-a-players="teamAPlayers"
                                            :team-b-players="teamBPlayers"
                                            :team-a-name="match.team_a_name"
                                            :team-b-name="match.team_b_name"
                                            :team-a-color="match.team_a_color"
                                            :team-b-color="match.team_b_color"
                                            :selected-player-id="selectedPlayerId"
                                            :recent-player-ids="recentPlayerIds"
                                            hide-headers
                                            @select="onPlayerSelected"
                                        />
                                    </template>

                                    <!-- Team-only buttons -->
                                    <div>
                                        <div v-if="eventScopes[pendingEventType] === 'player'" class="relative mb-3 flex items-center">
                                            <div class="flex-1 border-t border-border"></div>
                                            <span class="px-3 text-[10px] font-medium text-muted-foreground">o solo al equipo</span>
                                            <div class="flex-1 border-t border-border"></div>
                                        </div>
                                        <div class="flex gap-2">
                                            <button
                                                :disabled="submitting"
                                                class="flex min-h-[44px] flex-1 items-center justify-center gap-2 rounded-lg border border-border bg-card/80 text-sm font-medium transition-all active:scale-[0.97] hover:bg-accent disabled:opacity-30"
                                                @click="submitTeamOnlyEvent(pendingEventType!, 'a')"
                                            >
                                                <span class="size-3 rounded-sm" :style="{ backgroundColor: match.team_a_color ?? '#6b7280' }"></span>
                                                {{ match.team_a_name }}
                                            </button>
                                            <button
                                                :disabled="submitting"
                                                class="flex min-h-[44px] flex-1 items-center justify-center gap-2 rounded-lg border border-border bg-card/80 text-sm font-medium transition-all active:scale-[0.97] hover:bg-accent disabled:opacity-30"
                                                @click="submitTeamOnlyEvent(pendingEventType!, 'b')"
                                            >
                                                <span class="size-3 rounded-sm" :style="{ backgroundColor: match.team_b_color ?? '#6b7280' }"></span>
                                                {{ match.team_b_name }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ===== NO PENDING EVENT: show event grid ===== -->
                        <template v-else>
                            <div ref="eventGridRef"></div>
                            <EventTypeGrid
                                :events="allEventTypes"
                                :disabled="submitting"
                                :cols="4"
                                @select="onEventSelected"
                            />
                        </template>
                    </div>

                    <!-- Success Toast -->
                    <Transition
                        enter-active-class="transition-all duration-300 ease-out"
                        enter-from-class="opacity-0 -translate-y-2"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 -translate-y-2"
                    >
                        <div v-if="lastRecorded" class="flex items-center gap-2 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-2.5 text-sm text-emerald-400">
                            <Check class="size-4 shrink-0" />
                            <span class="truncate"><strong>{{ lastRecorded.player }}</strong> &mdash; {{ lastRecorded.event }} ({{ formatEventTime(lastRecorded.minute, lastRecorded.second) }})</span>
                        </div>
                    </Transition>

                    <!-- Recent events in fullscreen (last 5) -->
                    <div v-if="isFullscreen && sortedEventsDesc.length" class="mt-2">
                        <h3 class="mb-2 text-xs font-extrabold tracking-widest text-muted-foreground uppercase">
                            Recientes
                            <span class="ml-1 font-normal opacity-60">({{ Math.min(5, sortedEventsDesc.length) }})</span>
                        </h3>
                        <EventTimeline
                            :events="sortedEventsDesc.slice(0, 5)"
                            :match="match"
                            :club-ulid="club.ulid"
                            :match-base="`${base}/${match.ulid}`"
                            show-delete
                            @delete="confirmRemoveEvent"
                            @edit="openEditEvent"
                        />
                    </div>

                    <!-- Full timeline (hidden in fullscreen) -->
                    <div v-if="!isFullscreen" class="mt-2">
                        <h3 class="mb-3 text-xs font-extrabold tracking-widest text-muted-foreground uppercase">
                            Timeline
                            <span class="ml-1 font-normal opacity-60">({{ match.events?.length ?? 0 }})</span>
                        </h3>
                        <EventTimeline
                            :events="sortedEventsDesc"
                            :match="match"
                            :club-ulid="club.ulid"
                            :match-base="`${base}/${match.ulid}`"
                            show-delete
                            @delete="confirmRemoveEvent"
                            @edit="openEditEvent"
                        />
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- DIALOG: EDIT EVENT                         -->
            <!-- ========================================== -->
            <Dialog :open="!!editingEvent" @update:open="(v: boolean) => { if (!v) editingEvent = null; }">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Editar evento</DialogTitle>
                        <DialogDescription>Modifica los datos del evento.</DialogDescription>
                    </DialogHeader>
                    <form class="space-y-4" @submit.prevent="submitEditEvent">
                        <!-- Event type -->
                        <div class="grid gap-1.5">
                            <Label class="text-xs">Tipo de evento</Label>
                            <Select v-model="editForm.event_type">
                                <SelectTrigger class="h-9 text-sm">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="et in allEventTypes" :key="et.value" :value="et.value">
                                        {{ et.label.replace('\n', ' ') }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="editForm.errors.event_type" />
                        </div>

                        <!-- Minute / Second -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label class="text-xs">Minuto</Label>
                                <Input v-model.number="editForm.minute" type="number" min="0" max="200" class="h-9 text-sm" />
                                <InputError :message="editForm.errors.minute" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label class="text-xs">Segundo</Label>
                                <Input v-model.number="editForm.second" type="number" min="0" max="59" class="h-9 text-sm" />
                                <InputError :message="editForm.errors.second" />
                            </div>
                        </div>

                        <!-- Team (player/team scoped) -->
                        <div v-if="editEventScope !== 'neutral'" class="grid gap-1.5">
                            <Label class="text-xs">Equipo</Label>
                            <Select v-model="editForm.team">
                                <SelectTrigger class="h-9 text-sm">
                                    <SelectValue placeholder="Sin equipo" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="a">{{ match.team_a_name }}</SelectItem>
                                    <SelectItem value="b">{{ match.team_b_name }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="editForm.errors.team" />
                        </div>

                        <!-- Player (player scoped) -->
                        <div v-if="editEventScope === 'player'" class="grid gap-1.5">
                            <Label class="text-xs">Jugador</Label>
                            <Select :model-value="editForm.player_id ? String(editForm.player_id) : 'none'" @update:model-value="(v) => editForm.player_id = v === 'none' ? null : Number(v)">
                                <SelectTrigger class="h-9 text-sm">
                                    <SelectValue placeholder="Sin jugador" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Sin jugador</SelectItem>
                                    <SelectItem v-for="att in editTeamPlayers" :key="att.player_id" :value="String(att.player_id)">
                                        {{ att.player?.display_name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Related player (substitution) -->
                        <div v-if="editForm.event_type === 'substitution'" class="grid gap-1.5">
                            <Label class="text-xs">Jugador que ingresa</Label>
                            <Select :model-value="editForm.related_player_id ? String(editForm.related_player_id) : 'none'" @update:model-value="(v) => editForm.related_player_id = v === 'none' ? null : Number(v)">
                                <SelectTrigger class="h-9 text-sm">
                                    <SelectValue placeholder="Sin jugador" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Sin jugador</SelectItem>
                                    <SelectItem v-for="att in editTeamPlayers" :key="att.player_id" :value="String(att.player_id)">
                                        {{ att.player?.display_name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Notes -->
                        <div class="grid gap-1.5">
                            <Label class="text-xs">Notas (opcional)</Label>
                            <Textarea v-model="editForm.notes" placeholder="Descripcion del evento..." class="text-sm" rows="2" />
                        </div>

                        <div class="flex flex-col gap-2 pt-2">
                            <Button type="submit" class="w-full gap-2" :disabled="editForm.processing">
                                <Check class="size-4" />
                                Guardar cambios
                            </Button>
                            <Button type="button" variant="ghost" class="w-full" @click="editingEvent = null">Cancelar</Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- ========================================== -->
            <!-- DIALOG: DELETE EVENT CONFIRMATION          -->
            <!-- ========================================== -->
            <Dialog v-model:open="showDeleteEventDialog">
                <DialogContent class="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Eliminar evento</DialogTitle>
                        <DialogDescription>
                            {{ deletingEventLabel ? `Se eliminará "${deletingEventLabel}".` : 'Se eliminará este evento.' }}
                            Esta acción no se puede deshacer.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="flex flex-col gap-2 pt-2">
                        <Button variant="destructive" class="w-full gap-2" @click="executeDeleteEvent">
                            <Trash2 class="size-4" />
                            Eliminar evento
                        </Button>
                        <Button variant="ghost" class="w-full" @click="showDeleteEventDialog = false">Cancelar</Button>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- ========================================== -->
            <!-- TAB: JUGADORES (admin only)                -->
            <!-- ========================================== -->
            <div v-if="isAdmin && activeTab === 'jugadores' && !isFullscreen" class="mt-4">
                <!-- Registered players -->
                <p class="mb-2 text-[10px] font-bold tracking-widest text-muted-foreground uppercase">
                    Jugadores registrados ({{ confirmedPlayers.length }})
                </p>
                <div v-if="confirmedPlayers.length" class="mb-4 max-h-80 space-y-1.5 overflow-y-auto">
                    <div
                        v-for="att in confirmedPlayers"
                        :key="att.id"
                        class="flex items-center gap-2 rounded-lg border border-border bg-accent/50 px-2.5 py-2"
                    >
                        <span
                            class="flex size-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold text-white"
                            :style="{ backgroundColor: att.team ? teamColor(att.team as 'a' | 'b') : '#6b7280' }"
                        >{{ att.player?.jersey_number ?? att.player?.display_name?.charAt(0) }}</span>
                        <div class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">{{ att.player?.display_name }}</span>
                        </div>
                        <!-- Team badge (tappable to cycle) -->
                        <button
                            :disabled="changingTeamUlid === att.ulid"
                            class="shrink-0 rounded-md px-2 py-1 text-[10px] font-semibold transition-all active:scale-95 disabled:opacity-50"
                            :class="att.team
                                ? 'border border-border text-white'
                                : 'border border-dashed border-border text-muted-foreground'"
                            :style="att.team ? { backgroundColor: teamColor(att.team as 'a' | 'b') + '80' } : {}"
                            :title="`Cambiar equipo: ${teamLabel(att.team)}`"
                            @click="cyclePlayerTeam(att)"
                        >
                            {{ teamLabel(att.team) }}
                        </button>
                        <button
                            :disabled="removingAttendanceUlid === att.ulid"
                            class="shrink-0 rounded-md p-1 text-destructive/60 transition-colors hover:bg-destructive/10 hover:text-destructive disabled:opacity-50"
                            @click="removePlayerFromMatch(att.ulid)"
                        >
                            <UserMinus class="size-4" />
                        </button>
                    </div>
                </div>
                <p v-else class="mb-4 text-center text-xs text-muted-foreground">
                    No hay jugadores registrados.
                </p>

                <!-- Create new player inline -->
                <div class="mb-4">
                    <button
                        class="flex w-full items-center justify-center gap-1.5 rounded-lg border border-dashed border-border py-2 text-xs font-medium text-muted-foreground transition-colors hover:border-primary/50 hover:text-primary"
                        @click="showCreatePlayer = !showCreatePlayer"
                    >
                        <UserPlus class="size-3.5" />
                        {{ showCreatePlayer ? 'Cancelar' : 'Crear nuevo jugador' }}
                    </button>

                    <form v-if="showCreatePlayer" class="mt-2 space-y-3 rounded-lg border border-border bg-accent/30 p-3" @submit.prevent="submitCreatePlayer">
                        <div class="grid gap-1.5">
                            <Label for="new-player-name" class="text-xs">Nombre</Label>
                            <Input id="new-player-name" v-model="createPlayerForm.name" placeholder="Nombre del jugador" class="h-8 text-sm" required />
                            <InputError :message="createPlayerForm.errors.name" />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="grid gap-1.5">
                                <Label for="new-player-position" class="text-xs">Posicion</Label>
                                <Select v-model="createPlayerForm.position">
                                    <SelectTrigger id="new-player-position" class="h-8 text-sm">
                                        <SelectValue placeholder="Seleccionar" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Sin posición</SelectItem>
                                        <SelectItem v-for="pos in positions" :key="pos.value" :value="pos.value">
                                            {{ pos.label }} ({{ pos.value }})
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="createPlayerForm.errors.position" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="new-player-jersey" class="text-xs">Dorsal</Label>
                                <Input id="new-player-jersey" v-model="createPlayerForm.jersey_number" type="number" min="1" max="99" placeholder="#" class="h-8 text-sm" />
                                <InputError :message="createPlayerForm.errors.jersey_number" />
                            </div>
                        </div>
                        <Button type="submit" size="sm" class="w-full gap-1.5" :disabled="createPlayerForm.processing">
                            <UserPlus class="size-3.5" />
                            Crear jugador
                        </Button>
                    </form>
                </div>

                <!-- Add players from club -->
                <p class="mb-2 text-[10px] font-bold tracking-widest text-muted-foreground uppercase">
                    Agregar jugadores
                </p>
                <div class="relative mb-2">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <Search class="size-4 text-muted-foreground" />
                    </div>
                    <Input
                        v-model="playerSearchQuery"
                        placeholder="Buscar jugador..."
                        class="pl-9 text-sm"
                    />
                </div>
                <div v-if="filteredUnregisteredPlayers.length" class="max-h-72 divide-y divide-border/50 overflow-y-auto rounded-lg border border-border">
                    <div class="sticky top-0 z-10 flex items-center justify-end gap-3 border-b border-border bg-card/95 px-2 py-1 text-[9px] font-medium text-muted-foreground backdrop-blur-sm">
                        <span class="flex items-center gap-1"><span class="size-2.5 rounded-sm" :style="{ backgroundColor: match.team_a_color ?? undefined }"></span>{{ match.team_a_name }}</span>
                        <span class="flex items-center gap-1"><span class="size-2.5 rounded-sm" :style="{ backgroundColor: match.team_b_color ?? undefined }"></span>{{ match.team_b_name }}</span>
                        <span class="text-zinc-500">Sin eq.</span>
                    </div>
                    <div
                        v-for="player in filteredUnregisteredPlayers"
                        :key="player.id"
                        class="flex items-center gap-2 px-2 py-1.5"
                    >
                        <span
                            class="flex size-6 shrink-0 items-center justify-center rounded-full bg-muted text-[10px] font-bold text-muted-foreground"
                        >{{ player.jersey_number ?? player.display_name.charAt(0) }}</span>
                        <div class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">{{ player.display_name }}</span>
                            <span v-if="player.position_label || player.jersey_number" class="text-[10px] text-muted-foreground">
                                {{ [player.position_label, player.jersey_number ? `#${player.jersey_number}` : ''].filter(Boolean).join(' · ') }}
                            </span>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <button
                                :disabled="addingPlayerKey === `${player.id}-a`"
                                :title="`Agregar a ${match.team_a_name}`"
                                class="flex size-8 items-center justify-center rounded-lg border border-border shadow-sm transition-all active:scale-90 disabled:opacity-40"
                                @click="addPlayerToMatch(player.id, 'a')"
                            >
                                <span class="size-4 rounded-sm" :style="{ backgroundColor: match.team_a_color ?? '#6b7280' }"></span>
                            </button>
                            <button
                                :disabled="addingPlayerKey === `${player.id}-b`"
                                :title="`Agregar a ${match.team_b_name}`"
                                class="flex size-8 items-center justify-center rounded-lg border border-border shadow-sm transition-all active:scale-90 disabled:opacity-40"
                                @click="addPlayerToMatch(player.id, 'b')"
                            >
                                <span class="size-4 rounded-sm" :style="{ backgroundColor: match.team_b_color ?? '#6b7280' }"></span>
                            </button>
                            <button
                                :disabled="addingPlayerKey === `${player.id}-none`"
                                title="Sin equipo"
                                class="flex size-8 items-center justify-center rounded-lg border border-dashed border-zinc-600 text-zinc-500 transition-all active:scale-90 disabled:opacity-40"
                                @click="addPlayerToMatch(player.id, null)"
                            >
                                <Plus class="size-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
                <p v-else class="text-center text-xs text-muted-foreground">
                    {{ playerSearchQuery ? 'No se encontraron jugadores.' : 'Todos los jugadores activos ya están registrados.' }}
                </p>
            </div>

            <!-- ========================================== -->
            <!-- TAB: REELS (admin only)                    -->
            <!-- ========================================== -->
            <div v-if="isAdmin && activeTab === 'reels' && !isFullscreen" class="mt-4 space-y-3">
                <!-- Generate / regenerate auto reels -->
                <Button
                    v-if="hasVideoReady"
                    variant="outline"
                    size="sm"
                    class="w-full gap-1.5"
                    :disabled="generatingReels || hasProcessingReels"
                    @click="generateAutoReels"
                >
                    <Film class="size-3.5" />
                    {{ generatingReels ? 'Generando...' : displayReels.length ? 'Regenerar reels automáticos' : 'Generar reels automáticos' }}
                </Button>

                <!-- Create reel (admin — with title + player) -->
                <Dialog v-if="hasVideoReady" v-model:open="showManualClipForm">
                    <DialogTrigger as-child>
                        <button
                            class="flex w-full items-center gap-3 rounded-xl border border-dashed border-emerald-500/30 bg-emerald-500/5 px-4 py-3 text-left transition-all hover:border-emerald-500/50 hover:bg-emerald-500/10 active:scale-[0.98]"
                        >
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-500/20">
                                <Plus class="size-4 text-emerald-400" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-emerald-400">Crear reel</p>
                                <p class="text-xs text-emerald-300/60">Selecciona el momento del video</p>
                            </div>
                        </button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Crear reel</DialogTitle>
                            <DialogDescription>
                                Indica el minuto y segundo de la jugada.
                            </DialogDescription>
                        </DialogHeader>
                        <form class="space-y-4" @submit.prevent="submitManualClip">
                            <div class="grid gap-1.5">
                                <Label for="clip-title" class="text-xs">Título (opcional)</Label>
                                <Input id="clip-title" v-model="manualClipForm.title" placeholder="Se genera automáticamente" class="h-9 text-sm" />
                                <InputError :message="manualClipForm.errors.title" />
                            </div>
                            <div>
                                <div class="flex justify-center">
                                    <MinuteSecondInput
                                        ref="clipTimeInput"
                                        v-model:minute="manualClipForm.minute"
                                        v-model:second="manualClipForm.second"
                                        :manual-mode="true"
                                        always-expanded
                                        :max-seconds="videoMaxSeconds"
                                    />
                                </div>
                                <p class="mt-2 text-center text-xs text-muted-foreground">
                                    Se creará un clip de 25s (15s antes y 10s después)
                                </p>
                                <InputError :message="manualClipForm.errors.minute" />
                                <InputError :message="manualClipForm.errors.second" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="clip-player" class="text-xs">Jugador (opcional, se le asigna el reel)</Label>
                                <Select v-model="manualClipForm.player_id">
                                    <SelectTrigger id="clip-player" class="h-9 text-sm">
                                        <SelectValue placeholder="Sin jugador" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Sin jugador</SelectItem>
                                        <SelectItem v-for="p in players" :key="p.id" :value="String(p.id)">
                                            {{ p.display_name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="admin-notes" class="text-xs">Notas (opcional)</Label>
                                <Textarea id="admin-notes" v-model="manualClipForm.request_notes" placeholder="Describe la jugada..." class="text-sm" rows="2" />
                            </div>
                            <div class="flex flex-col gap-2 pt-2">
                                <Button type="submit" class="w-full gap-2" :disabled="manualClipForm.processing || clipTimeInput?.isOverMax">
                                    <Film class="size-4" />
                                    Crear reel
                                </Button>
                                <DialogClose as-child>
                                    <Button type="button" variant="ghost" class="w-full">Cancelar</Button>
                                </DialogClose>
                            </div>
                        </form>
                    </DialogContent>
                </Dialog>

                <!-- Reels list -->
                <InfiniteScroll data="reels" #default="{ loading: fetching }">
                <div class="space-y-3">
                    <div
                        v-for="reel in displayReels"
                        :key="reel.ulid"
                        class="overflow-hidden rounded-xl border border-border"
                    >
                        <div class="bg-card px-3 py-2.5">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold">{{ reel.title }}</p>
                                    <p v-if="reel.request_notes" class="mt-0.5 text-xs text-foreground/70">{{ reel.request_notes }}</p>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                                        <span class="inline-flex items-center rounded border px-1.5 py-0.5 text-[10px] font-medium" :class="sourceBadgeClass(reel.source)">
                                            {{ sourceLabel(reel.source) }}
                                        </span>
                                        <span>{{ formatSeconds(reel.start_second) }} - {{ formatSeconds(reel.end_second) }}</span>
                                        <span v-if="reel.player">· {{ reel.player.display_name }}</span>
                                        <span v-if="reel.status === 'completed'" class="inline-flex items-center gap-0.5"><Eye class="size-3" /> {{ reelViewCount(reel) }}</span>
                                    </div>
                                </div>
                                <span
                                    v-if="reel.status !== 'completed'"
                                    class="mt-0.5 inline-flex shrink-0 items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                                    :class="{
                                        'border-amber-500/30 bg-amber-500/10 text-amber-400': reel.status === 'pending',
                                        'border-blue-500/30 bg-blue-500/10 text-blue-400 animate-pulse': reel.status === 'processing',
                                        'border-red-500/30 bg-red-500/10 text-red-400': reel.status === 'failed',
                                    }"
                                >
                                    {{ reel.status === 'pending' ? 'En cola' : reel.status === 'processing' ? 'Procesando...' : 'Falló' }}
                                </span>
                            </div>
                        </div>

                        <!-- Processing -->
                        <div v-if="reel.status === 'processing'" class="border-t border-blue-500/20 bg-blue-500/5 px-3 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <div class="size-4 animate-spin rounded-full border-2 border-blue-500/30 border-t-blue-400"></div>
                                <p class="text-xs text-blue-400">Generando reel...</p>
                            </div>
                            <div class="mt-2 flex gap-2">
                                <button
                                    class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-blue-500/30 py-1.5 text-xs font-medium text-blue-400 transition-colors hover:bg-blue-500/10 disabled:opacity-50"
                                    :disabled="refreshingReels"
                                    @click="refreshReels"
                                >
                                    <RefreshCw class="size-3" :class="refreshingReels ? 'animate-spin' : ''" />
                                    Actualizar
                                </button>
                                <button
                                    class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-red-500/30 py-1.5 text-xs font-medium text-red-400 transition-colors hover:bg-red-500/10"
                                    :disabled="deletingReelUlid === reel.ulid"
                                    @click="deleteReel(reel)"
                                >
                                    <X class="size-3" />
                                    Cancelar
                                </button>
                            </div>
                        </div>

                        <!-- Pending -->
                        <div v-else-if="reel.status === 'pending'" class="border-t border-amber-500/20 bg-amber-500/5 px-3 py-3">
                            <p class="text-center text-xs text-amber-400">Reel en cola, se procesará pronto.</p>
                            <div class="mt-2 flex gap-2">
                                <button
                                    class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-amber-500/30 py-1.5 text-xs font-medium text-amber-400 transition-colors hover:bg-amber-500/10 disabled:opacity-50"
                                    :disabled="refreshingReels"
                                    @click="refreshReels"
                                >
                                    <RefreshCw class="size-3" :class="refreshingReels ? 'animate-spin' : ''" />
                                    Actualizar
                                </button>
                                <button
                                    class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-red-500/30 py-1.5 text-xs font-medium text-red-400 transition-colors hover:bg-red-500/10"
                                    :disabled="deletingReelUlid === reel.ulid"
                                    @click="deleteReel(reel)"
                                >
                                    <X class="size-3" />
                                    Cancelar
                                </button>
                            </div>
                        </div>

                        <!-- Failed -->
                        <div v-else-if="reel.status === 'failed'" class="border-t border-red-500/20 bg-red-500/5 px-3 py-3">
                            <p class="text-center text-xs text-red-400">No se pudo generar el reel. Intenta crearlo de nuevo.</p>
                            <button
                                class="mt-2 flex w-full items-center justify-center gap-1.5 rounded-lg border border-red-500/30 py-1.5 text-xs font-medium text-red-400 transition-colors hover:bg-red-500/10"
                                :disabled="deletingReelUlid === reel.ulid"
                                @click="deleteReel(reel)"
                            >
                                <Trash2 class="size-3" />
                                Eliminar
                            </button>
                        </div>

                        <!-- Completed -->
                        <template v-else-if="reel.status === 'completed'">
                            <div v-if="reel.media_url" class="border-t border-border bg-black">
                                <video
                                    :src="reelMediaUrl(reel)!"
                                    controls
                                    preload="metadata"
                                    class="mx-auto max-h-80 w-full"
                                    @play="trackReelView(reel)"
                                ></video>
                            </div>
                            <div v-if="reel.media_url" class="flex items-center gap-2 border-t border-border bg-card/50 px-3 py-2.5">
                                <button
                                    class="inline-flex items-center gap-1 rounded-md px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                                    @click="downloadReel(reel)"
                                >
                                    <Download class="size-3.5" />
                                    Descargar
                                </button>
                                <button
                                    v-if="confirmDeleteReelUlid !== reel.ulid"
                                    class="inline-flex items-center gap-1 rounded-md px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                                    @click="onDeleteReelClick(reel)"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                                <button
                                    v-else
                                    class="inline-flex items-center gap-1 rounded-md bg-red-500/10 px-2.5 py-1.5 text-xs font-semibold text-red-400 transition-colors hover:bg-red-500/20"
                                    :disabled="deletingReelUlid === reel.ulid"
                                    @click="deleteReel(reel)"
                                >
                                    Confirmar
                                </button>
                                <button
                                    class="ml-auto inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition-all hover:bg-emerald-500 active:scale-95"
                                    @click="shareReel(reel)"
                                >
                                    <Share2 class="size-4" />
                                    Compartir
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                <p v-if="!displayReels.length" class="text-center text-sm text-muted-foreground">
                    No hay reels generados.
                </p>
                <div v-if="fetching" class="py-4 text-center text-sm text-muted-foreground">
                    Cargando más reels...
                </div>
                </InfiniteScroll>
            </div>

            <!-- ========================================== -->
            <!-- TAB: LO MEJOR (non-admin)                 -->
            <!-- ========================================== -->
            <div v-if="!isAdmin && activeTab === 'reels' && !isFullscreen" class="mt-4 space-y-3">
                <InfiniteScroll data="reels" #default="{ loading: fetchingReels }">
                <div class="space-y-3">
                <div
                    v-for="reel in completedReels"
                    :key="reel.ulid"
                    class="overflow-hidden rounded-xl border border-border"
                >
                    <div class="bg-card px-3 py-2">
                        <p class="text-sm font-semibold">{{ reel.title }}</p>
                        <p v-if="reel.request_notes" class="mt-0.5 text-xs text-foreground/70">{{ reel.request_notes }}</p>
                        <div class="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
                            <span v-if="reel.player">{{ reel.player.display_name }}</span>
                            <span class="inline-flex items-center gap-0.5"><Eye class="size-3" /> {{ reelViewCount(reel) }}</span>
                        </div>
                    </div>
                    <div v-if="reel.media_url" class="border-t border-border bg-black">
                        <video
                            :src="reelMediaUrl(reel)!"
                            controls
                            preload="metadata"
                            class="mx-auto max-h-80 w-full"
                            @play="trackReelView(reel)"
                        ></video>
                    </div>
                    <div v-if="reel.media_url" class="flex items-center gap-2 border-t border-border bg-card/50 px-3 py-2.5">
                        <a
                            :href="reelMediaUrl(reel)!"
                            download
                            class="inline-flex items-center gap-1 rounded-md px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        >
                            <Download class="size-3.5" />
                            Descargar
                        </a>
                        <button
                            class="ml-auto inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition-all hover:bg-emerald-500 active:scale-95"
                            @click="shareReel(reel)"
                        >
                            <Share2 class="size-4" />
                            Compartir
                        </button>
                    </div>
                </div>
                </div>

                <p v-if="!completedReels.length" class="text-center text-sm text-muted-foreground">
                    Aún no hay jugadas disponibles.
                </p>
                <div v-if="fetchingReels" class="py-4 text-center text-sm text-muted-foreground">
                    Cargando más jugadas...
                </div>
                </InfiniteScroll>
            </div>

        </div>
    </component>
</template>
