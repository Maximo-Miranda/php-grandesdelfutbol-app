export type NewsSource = {
    id: number;
    name: string;
    slug: string;
    logo_url: string | null;
};

export type NewsArticle = {
    id: number;
    ulid: string;
    slug: string;
    title: string;
    snippet: string | null;
    image_url: string | null;
    original_url: string;
    author: string | null;
    content_type: 'article' | 'video_highlight';
    video_embed_url: string | null;
    tags: string[] | null;
    competitions: string[] | null;
    teams: string[] | null;
    topics: string[] | null;
    is_breaking: boolean;
    ai_summary: string | null;
    story_group_id: string | null;
    published_at: string;
    created_at: string;
    source?: NewsSource;
    story_source_count?: number;
};

export type UserNewsPreference = {
    id: number;
    competitions: string[] | null;
    teams: string[] | null;
    topics: string[] | null;
    free_text_input: string | null;
    onboarding_completed: boolean;
};

export type NewsAdPlacement = {
    id: number;
    name: string;
    advertiser: string;
    image_url: string;
    target_url: string;
};
