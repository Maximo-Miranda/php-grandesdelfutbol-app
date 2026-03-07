export type Venue = {
    id: number;
    ulid: string;
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
    ulid: string;
    venue_id: number;
    name: string;
    field_type: '5v5' | '6v6' | '7v7' | '8v8' | '9v9' | '10v10' | '11v11';
    surface_type: string | null;
    is_active: boolean;
    venue?: Venue;
    created_at: string;
    updated_at: string;
};
