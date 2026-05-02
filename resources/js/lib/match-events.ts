import {
    AlertTriangle,
    ArrowLeftRight,
    CircleDot,
    CornerDownRight,
    Crosshair,
    Droplets,
    Flag,
    FlagOff,
    Hand,
    Pause,
    Play,
    RectangleVertical,
    Shield,
    Timer,
    X,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import type { FootballMatch, MatchEvent } from '@/types';

export const EVENT_LABELS: Record<string, string> = {
    goal: 'Gol',
    assist: 'Asistencia',
    yellow_card: 'Tarjeta amarilla',
    red_card: 'Tarjeta roja',
    blue_card: 'Tarjeta azul',
    penalty_scored: 'Penal anotado',
    penalty_missed: 'Penal fallado',
    own_goal: 'Autogol',
    save: 'Atajada',
    free_kick: 'Tiro libre',
    substitution: 'Cambio',
    injury: 'Lesion',
    foul: 'Falta',
    handball: 'Mano',
    shot_on_target: 'Tiro al marco',
    corner_kick: 'Tiro de esquina',
    throw_in: 'Saque de banda',
    offside: 'Fuera de juego',
    team_foul: 'Falta (equipo)',
    team_handball: 'Mano (equipo)',
    team_penalty: 'Penal (equipo)',
    timeout: 'Tiempo',
    ball_touched_referee: 'Balon toco arbitro',
    stoppage_start: 'Juego detenido',
    stoppage_end: 'Juego reanudado',
    water_break: 'Pausa hidratacion',
    match_start: 'Inicio del partido',
    first_half_end: 'Fin del primer tiempo',
    second_half_start: 'Inicio del segundo tiempo',
    match_end: 'Fin del partido',
};

export const EVENT_ICON_COLORS: Record<string, string> = {
    goal: 'text-emerald-400',
    assist: 'text-sky-400',
    yellow_card: 'text-yellow-400',
    red_card: 'text-red-400',
    blue_card: 'text-blue-400',
    penalty_scored: 'text-emerald-300',
    penalty_missed: 'text-zinc-400',
    own_goal: 'text-orange-400',
    save: 'text-violet-400',
    free_kick: 'text-cyan-400',
    substitution: 'text-blue-400',
    injury: 'text-rose-400',
    foul: 'text-amber-400',
    handball: 'text-orange-300',
    shot_on_target: 'text-teal-400',
    corner_kick: 'text-indigo-400',
    throw_in: 'text-slate-400',
    offside: 'text-pink-400',
    team_foul: 'text-amber-400',
    team_handball: 'text-orange-300',
    team_penalty: 'text-red-300',
    timeout: 'text-zinc-300',
    ball_touched_referee: 'text-zinc-400',
    stoppage_start: 'text-yellow-300',
    stoppage_end: 'text-emerald-300',
    water_break: 'text-blue-300',
    match_start: 'text-emerald-300',
    first_half_end: 'text-amber-300',
    second_half_start: 'text-emerald-300',
    match_end: 'text-zinc-300',
};

const ballEvents = new Set(['goal', 'assist', 'penalty_scored', 'penalty_missed', 'free_kick']);
const cardEvents = new Set(['yellow_card', 'red_card', 'blue_card']);
const swapEvents = new Set(['substitution', 'throw_in']);
const foulEvents = new Set(['foul', 'team_foul']);
const handEvents = new Set(['handball', 'team_handball']);

export const EVENT_ICON_MAP: Record<string, Component> = {};
for (const type of ballEvents) EVENT_ICON_MAP[type] = CircleDot;
for (const type of cardEvents) EVENT_ICON_MAP[type] = RectangleVertical;
for (const type of swapEvents) EVENT_ICON_MAP[type] = ArrowLeftRight;
for (const type of foulEvents) EVENT_ICON_MAP[type] = X;
for (const type of handEvents) EVENT_ICON_MAP[type] = Hand;
EVENT_ICON_MAP['injury'] = AlertTriangle;
EVENT_ICON_MAP['shot_on_target'] = Crosshair;
EVENT_ICON_MAP['corner_kick'] = CornerDownRight;
EVENT_ICON_MAP['offside'] = Flag;
EVENT_ICON_MAP['timeout'] = Timer;
EVENT_ICON_MAP['stoppage_start'] = Pause;
EVENT_ICON_MAP['stoppage_end'] = Play;
EVENT_ICON_MAP['water_break'] = Droplets;
EVENT_ICON_MAP['own_goal'] = Shield;
EVENT_ICON_MAP['save'] = Shield;
EVENT_ICON_MAP['ball_touched_referee'] = Hand;
EVENT_ICON_MAP['match_start'] = Play;
EVENT_ICON_MAP['first_half_end'] = Flag;
EVENT_ICON_MAP['second_half_start'] = Play;
EVENT_ICON_MAP['match_end'] = FlagOff;

export const EVENT_ICON_FALLBACK: Component = Shield;

export type EventScope = 'player' | 'team' | 'neutral';

export const EVENT_SCOPES: Record<string, EventScope> = {
    goal: 'player', assist: 'player', yellow_card: 'player', red_card: 'player',
    blue_card: 'player',
    penalty_scored: 'player', penalty_missed: 'player', own_goal: 'player',
    save: 'player', free_kick: 'player', substitution: 'player', injury: 'player',
    foul: 'player', handball: 'player',
    shot_on_target: 'team', corner_kick: 'team', throw_in: 'team', offside: 'team',
    timeout: 'neutral', ball_touched_referee: 'neutral', stoppage_start: 'neutral',
    stoppage_end: 'neutral', water_break: 'neutral',
    match_start: 'neutral', first_half_end: 'neutral', second_half_start: 'neutral',
    match_end: 'neutral',
};

const EVENTS_WITH_OPTIONAL_TEAM = new Set<string>(['timeout']);

export function allowsOptionalTeam(eventType: string): boolean {
    return EVENTS_WITH_OPTIONAL_TEAM.has(eventType);
}

export type EventGridButton = {
    value: string;
    label: string;
    icon: Component;
    color: string;
    bg: string;
};

export const ALL_EVENT_TYPES: EventGridButton[] = [
    { value: 'goal', label: 'Gol', icon: CircleDot, color: 'text-emerald-400', bg: 'bg-emerald-500/10 border-emerald-500/30 hover:bg-emerald-500/20' },
    { value: 'assist', label: 'Asist.', icon: CircleDot, color: 'text-sky-400', bg: 'bg-sky-500/10 border-sky-500/30 hover:bg-sky-500/20' },
    { value: 'yellow_card', label: 'Amarilla', icon: RectangleVertical, color: 'text-yellow-400', bg: 'bg-yellow-500/10 border-yellow-500/30 hover:bg-yellow-500/20' },
    { value: 'red_card', label: 'Roja', icon: RectangleVertical, color: 'text-red-400', bg: 'bg-red-500/10 border-red-500/30 hover:bg-red-500/20' },
    { value: 'blue_card', label: 'Azul', icon: RectangleVertical, color: 'text-blue-400', bg: 'bg-blue-500/10 border-blue-500/30 hover:bg-blue-500/20' },
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
    { value: 'stoppage_start', label: 'Juego\ndetenido', icon: Pause, color: 'text-yellow-300', bg: 'bg-yellow-500/10 border-yellow-500/30 hover:bg-yellow-500/20' },
    { value: 'stoppage_end', label: 'Juego\nreanudado', icon: Play, color: 'text-emerald-300', bg: 'bg-emerald-500/10 border-emerald-500/30 hover:bg-emerald-500/20' },
    { value: 'water_break', label: 'Hidratacion', icon: Droplets, color: 'text-blue-300', bg: 'bg-blue-500/10 border-blue-500/30 hover:bg-blue-500/20' },
    { value: 'ball_touched_referee', label: 'Balon\narbitro', icon: Hand, color: 'text-zinc-400', bg: 'bg-zinc-500/10 border-zinc-500/30 hover:bg-zinc-500/20' },
    { value: 'match_start', label: 'Inicio\n1º T', icon: Play, color: 'text-emerald-300', bg: 'bg-emerald-500/10 border-emerald-500/30 hover:bg-emerald-500/20' },
    { value: 'first_half_end', label: 'Fin\n1º T', icon: Flag, color: 'text-amber-300', bg: 'bg-amber-500/10 border-amber-500/30 hover:bg-amber-500/20' },
    { value: 'second_half_start', label: 'Inicio\n2º T', icon: Play, color: 'text-emerald-300', bg: 'bg-emerald-500/10 border-emerald-500/30 hover:bg-emerald-500/20' },
    { value: 'match_end', label: 'Fin\npartido', icon: FlagOff, color: 'text-zinc-300', bg: 'bg-zinc-500/10 border-zinc-500/30 hover:bg-zinc-500/20' },
];

export type EventPanelColor = { border: string; bg: string; bgLight: string; text: string; textLight: string };

export const EVENT_PANEL_COLORS: Record<string, EventPanelColor> = {
    goal: { border: 'border-emerald-500/30', bg: 'bg-emerald-500/10', bgLight: 'bg-emerald-500/5', text: 'text-emerald-400', textLight: 'text-emerald-300/70' },
    assist: { border: 'border-sky-500/30', bg: 'bg-sky-500/10', bgLight: 'bg-sky-500/5', text: 'text-sky-400', textLight: 'text-sky-300/70' },
    yellow_card: { border: 'border-yellow-500/30', bg: 'bg-yellow-500/10', bgLight: 'bg-yellow-500/5', text: 'text-yellow-400', textLight: 'text-yellow-300/70' },
    red_card: { border: 'border-red-500/30', bg: 'bg-red-500/10', bgLight: 'bg-red-500/5', text: 'text-red-400', textLight: 'text-red-300/70' },
    blue_card: { border: 'border-blue-500/30', bg: 'bg-blue-500/10', bgLight: 'bg-blue-500/5', text: 'text-blue-400', textLight: 'text-blue-300/70' },
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
    timeout: { border: 'border-zinc-500/30', bg: 'bg-zinc-500/10', bgLight: 'bg-zinc-500/5', text: 'text-zinc-300', textLight: 'text-zinc-300/70' },
};

export const DEFAULT_PANEL_COLOR: EventPanelColor = {
    border: 'border-amber-500/30', bg: 'bg-amber-500/10', bgLight: 'bg-amber-500/5', text: 'text-amber-400', textLight: 'text-amber-300/70',
};

export function getEventIconColor(eventType: string): string {
    return EVENT_ICON_COLORS[eventType] ?? 'text-muted-foreground';
}

export function getEventIcon(eventType: string): Component {
    return EVENT_ICON_MAP[eventType] ?? EVENT_ICON_FALLBACK;
}

export function countTeamGoals(match: FootballMatch, team: 'a' | 'b'): number {
    const opposite = team === 'a' ? 'b' : 'a';
    return (match.events ?? []).filter((e: MatchEvent) => {
        const isGoalType = e.event_type === 'goal' || e.event_type === 'penalty_scored';
        const isOwnGoal = e.event_type === 'own_goal';
        if (!isGoalType && !isOwnGoal) return false;

        if (!e.player_id) {
            if (isGoalType) return e.team === team;
            if (isOwnGoal) return e.team === opposite;
            return false;
        }

        const playerTeam = match.attendances?.find(a => a.player_id === e.player_id)?.team;
        if (isGoalType) return playerTeam === team;
        if (isOwnGoal) return playerTeam === opposite;
        return false;
    }).length;
}

export function getEventTeam(match: FootballMatch, event: MatchEvent): 'a' | 'b' | null {
    if (event.team) return event.team;
    if (!event.player_id) return null;
    const att = match.attendances?.find(a => a.player_id === event.player_id);
    return (att?.team as 'a' | 'b') ?? null;
}
