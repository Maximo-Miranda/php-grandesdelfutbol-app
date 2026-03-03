import type { Player } from './player';
import type { Field } from './venue';

export type FootballMatch = {
    id: number;
    club_id: number;
    field_id: number | null;
    title: string;
    scheduled_at: string;
    duration_minutes: number;
    arrival_minutes: number;
    max_players: number;
    max_substitutes: number;
    status: 'upcoming' | 'in_progress' | 'completed' | 'cancelled';
    share_token: string | null;
    registration_opens_hours: number;
    notes: string | null;
    started_at: string | null;
    ended_at: string | null;
    stats_finalized_at: string | null;
    team_a_name: string;
    team_b_name: string;
    team_a_color: string | null;
    team_b_color: string | null;
    field?: Field;
    attendances?: MatchAttendance[];
    events?: MatchEvent[];
    attendances_count?: number;
    created_at: string;
    updated_at: string;
};

export type MatchAttendance = {
    id: number;
    match_id: number;
    player_id: number;
    status: 'confirmed' | 'declined';
    role: 'pending' | 'starter' | 'substitute';
    team: 'a' | 'b' | null;
    confirmed_at: string | null;
    player?: Player;
    created_at: string;
    updated_at: string;
};

export type MatchEvent = {
    id: number;
    match_id: number;
    player_id: number;
    event_type: 'goal' | 'assist' | 'yellow_card' | 'red_card' | 'penalty_scored' | 'penalty_missed' | 'free_kick' | 'save' | 'own_goal';
    minute: number;
    notes: string | null;
    player?: Player;
    created_at: string;
    updated_at: string;
};
