export type Venue = {
    id: number;
    club_id: number;
    name: string;
    address: string | null;
    map_link: string | null;
    notes: string | null;
    is_active: boolean;
    fields?: Field[];
    created_at: string;
    updated_at: string;
};

export type Field = {
    id: number;
    venue_id: number;
    name: string;
    field_type: '5v5' | '7v7' | '11v11';
    surface_type: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
};
