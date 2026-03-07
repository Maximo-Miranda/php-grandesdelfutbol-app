import type { User } from './auth';
import type { Club } from './club';

export type Player = {
    id: number;
    ulid: string;
    club_id: number;
    user_id: number | null;
    name: string;
    display_name: string;
    photo_url: string | null;
    position: string | null;
    position_label: string | null;
    jersey_number: number | null;
    goals: number;
    assists: number;
    matches_played: number;
    yellow_cards: number;
    red_cards: number;
    is_active: boolean;
    user?: User;
    club?: Club;
    created_at: string;
    updated_at: string;
};

export type PlayerProfile = {
    id: number;
    user_id: number;
    nickname: string | null;
    gender: 'male' | 'female' | 'other' | null;
    date_of_birth: string | null;
    id_type: string | null;
    id_number: string | null;
    nationality: string | null;
    bio: string | null;
    preferred_position: string | null;
    created_at: string;
    updated_at: string;
};
