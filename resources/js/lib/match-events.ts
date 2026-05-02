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
