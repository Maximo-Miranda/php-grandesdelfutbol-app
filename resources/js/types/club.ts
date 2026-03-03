import type { User } from './auth';

export type Club = {
    id: number;
    name: string;
    description: string | null;
    owner_id: number;
    invite_token: string | null;
    is_invite_active: boolean;
    requires_approval: boolean;
    logo_url?: string | null;
    owner?: User;
    members_count?: number;
    matches_count?: number;
    created_at: string;
    updated_at: string;
};

export type ClubMember = {
    id: number;
    club_id: number;
    user_id: number;
    role: 'owner' | 'admin' | 'player';
    status: 'pending' | 'approved';
    approved_at: string | null;
    user?: User;
    club?: Club;
    created_at: string;
    updated_at: string;
};

export type ClubInvitation = {
    id: number;
    club_id: number;
    email: string;
    token: string;
    status: 'pending' | 'accepted' | 'declined' | 'expired';
    invited_by: number;
    expires_at: string;
    club?: Club;
    created_at: string;
    updated_at: string;
};
