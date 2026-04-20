export type StatCode = 'PJ' | 'G' | 'E' | 'P' | 'GF' | 'GC' | 'DG' | 'Pts';

type StatStyle = {
    /** Used for chips (legend), with border + bg + text */
    chip: string;
    /** Used for the small abbreviation label above the value */
    label: string;
};

/**
 * Subtle semantic tints for stat abbreviations. Colors are intentionally muted
 * (low opacity) so the values stay easy to read; the tinted labels act as a
 * mnemonic hint, not a statement. Pts is the only exception — it stays vivid
 * because it's the headline metric.
 */
export const STAT_STYLES: Record<StatCode, StatStyle> = {
    PJ: {
        chip: 'border-border bg-background text-muted-foreground',
        label: 'text-muted-foreground',
    },
    G: {
        chip: 'border-emerald-500/25 bg-emerald-500/5 text-emerald-500/80',
        label: 'text-emerald-500/70',
    },
    E: {
        chip: 'border-amber-500/25 bg-amber-500/5 text-amber-500/80',
        label: 'text-amber-500/70',
    },
    P: {
        chip: 'border-rose-500/25 bg-rose-500/5 text-rose-500/80',
        label: 'text-rose-500/70',
    },
    GF: {
        chip: 'border-emerald-500/20 bg-emerald-500/5 text-emerald-500/70',
        label: 'text-emerald-500/55',
    },
    GC: {
        chip: 'border-rose-500/20 bg-rose-500/5 text-rose-500/70',
        label: 'text-rose-500/55',
    },
    DG: {
        chip: 'border-border bg-background text-muted-foreground',
        label: 'text-muted-foreground',
    },
    Pts: {
        chip: 'border-primary/40 bg-primary/10 text-primary',
        label: 'text-primary',
    },
};
