<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, Calendar, ChevronRight, Shield, Trophy, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import { Badge } from '@/components/ui/badge';
import UserAvatar from '@/components/UserAvatar.vue';
import { buildCanonicalUrl, formatDate as formatDateUtil, truncateForMeta } from '@/lib/utils';
import StandingsLegend from '@/pages/clubs/standings/partials/StandingsLegend.vue';

type Player = {
    id: number;
    ulid: string;
    name: string;
    jersey_number: number | null;
    position: string | null;
    photo_url: string | null;
};

type PublicTeam = {
    id: number;
    ulid: string;
    name: string;
    color: string;
    bio: string | null;
    logo_url: string | null;
    cover_url: string | null;
    season: { ulid: string; name: string; is_active: boolean };
    coach: Player | null;
    captain: Player | null;
    players: Player[];
    players_count: number;
};

type PublicClub = {
    ulid: string;
    slug: string;
    name: string;
    logo_url: string | null;
};

type Stats = {
    PJ: number; G: number; E: number; P: number;
    GF: number; GC: number; DG: number; Pts: number;
};

type RecentMatch = {
    ulid: string;
    title: string | null;
    scheduled_at: string;
    team_a_id: number | null;
    team_b_id: number | null;
    team_a_name: string | null;
    team_b_name: string | null;
    team_a_score: number | null;
    team_b_score: number | null;
    status: string;
    is_friendly: boolean;
    share_token: string | null;
};

const props = defineProps<{
    team: PublicTeam;
    club: PublicClub;
    stats: Stats;
    recentMatches: RecentMatch[];
    appUrl: string;
}>();

const canonicalUrl = computed(() => buildCanonicalUrl(props.appUrl, `/team/${props.team.ulid}`));

const seoTitle = computed(() => `${props.team.name} — ${props.club.name}`);

const seoDescription = computed(() => {
    if (props.team.bio && props.team.bio.trim().length > 0) {
        return truncateForMeta(props.team.bio);
    }

    return `${props.team.name} — ${props.club.name}. ${props.team.players_count} jugadores, ${props.stats.PJ} partidos jugados, ${props.stats.Pts} puntos en ${props.team.season.name}.`;
});

const ogImage = computed(() => props.team.logo_url ?? props.team.cover_url ?? props.club.logo_url ?? buildCanonicalUrl(props.appUrl, '/pwa-512x512.png'));

const jsonLd = computed(() =>
    JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'SportsTeam',
        name: props.team.name,
        sport: 'Football',
        url: canonicalUrl.value,
        logo: props.team.logo_url ?? undefined,
        description: seoDescription.value,
        memberOf: {
            '@type': 'SportsTeam',
            name: props.club.name,
            url: buildCanonicalUrl(props.appUrl, `/club/${props.club.slug}`),
        },
        numberOfEmployees: props.team.players_count,
    }),
);

type MatchResult = 'W' | 'D' | 'L' | 'F' | 'U';

type PresentedMatch = {
    ulid: string;
    date: string;
    opponentName: string;
    myScore: number | null;
    theirScore: number | null;
    isHome: boolean;
    result: MatchResult;
    isFriendly: boolean;
    status: string;
    share_token: string | null;
};

const presentedMatches = computed<PresentedMatch[]>(() => props.recentMatches.map((m): PresentedMatch => {
    const isHome = m.team_a_id === props.team.id;
    const myScore = isHome ? m.team_a_score : m.team_b_score;
    const theirScore = isHome ? m.team_b_score : m.team_a_score;
    const opponentName = (isHome ? m.team_b_name : m.team_a_name) ?? 'Rival externo';

    let result: MatchResult = 'U';
    if (m.is_friendly) {
        result = 'F';
    } else if (myScore !== null && theirScore !== null) {
        if (myScore > theirScore) result = 'W';
        else if (myScore < theirScore) result = 'L';
        else result = 'D';
    }

    return {
        ulid: m.ulid,
        date: m.scheduled_at,
        opponentName,
        myScore,
        theirScore,
        isHome,
        result,
        isFriendly: m.is_friendly,
        status: m.status,
        share_token: m.share_token,
    };
}));

const resultStyles: Record<MatchResult, { cls: string; label: string; letter: string }> = {
    W: { cls: 'bg-emerald-500/15 text-emerald-500 ring-1 ring-emerald-500/40', label: 'Victoria', letter: 'V' },
    D: { cls: 'bg-amber-500/15 text-amber-500 ring-1 ring-amber-500/40', label: 'Empate', letter: 'E' },
    L: { cls: 'bg-rose-500/15 text-rose-500 ring-1 ring-rose-500/40', label: 'Derrota', letter: 'D' },
    F: { cls: 'bg-muted text-muted-foreground ring-1 ring-border', label: 'Amistoso', letter: 'A' },
    U: { cls: 'bg-muted/40 text-muted-foreground ring-1 ring-border', label: 'Pendiente', letter: '-' },
};

function formatDate(iso: string): string {
    return formatDateUtil(iso, { day: 'numeric', month: 'short', year: 'numeric' });
}

function matchHref(m: PresentedMatch): string | null {
    return m.share_token ? `/match/${m.share_token}` : null;
}

function playerHref(playerUlid: string): string {
    return `/player/${playerUlid}?from=team:${props.team.ulid}`;
}
</script>

<template>
    <SeoHead
        :title="seoTitle"
        :description="seoDescription"
        :canonical-url="canonicalUrl"
        :og-image="ogImage"
    />

    <div class="min-h-screen bg-background text-foreground">
        <!-- eslint-disable-next-line vue/no-v-text-v-html-on-component -->
        <component :is="'script'" type="application/ld+json" v-html="jsonLd" />

        <PublicHeader />

        <!-- Hero: cover / team color with champions accent -->
        <section
            class="relative h-56 w-full overflow-hidden pt-16 sm:h-72"
            :style="team.cover_url
                ? { backgroundImage: `url(${team.cover_url})`, backgroundSize: 'cover', backgroundPosition: 'center' }
                : { background: `radial-gradient(120% 80% at 20% 0%, ${team.color} 0%, ${team.color}cc 35%, #0b1220 85%)` }"
        >
            <!-- Diagonal pattern overlay -->
            <div
                v-if="!team.cover_url"
                class="pointer-events-none absolute inset-0 opacity-[0.12] mix-blend-overlay"
                style="background-image: repeating-linear-gradient(135deg, transparent 0, transparent 18px, rgba(255,255,255,0.5) 18px, rgba(255,255,255,0.5) 19px), repeating-linear-gradient(45deg, transparent 0, transparent 18px, rgba(255,255,255,0.3) 18px, rgba(255,255,255,0.3) 19px);"
            />
            <div class="pointer-events-none absolute -right-20 -top-20 size-60 rounded-full" :style="{ background: `radial-gradient(circle, ${team.color}66 0%, transparent 70%)` }" />
            <div class="pointer-events-none absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-background via-background/60 to-transparent" />
            <div class="absolute inset-x-0 bottom-0 h-0.5 bg-border" />
            <div class="absolute inset-x-0 bottom-0 h-1" :style="{ background: `linear-gradient(90deg, transparent 0%, ${team.color} 50%, transparent 100%)` }" />

            <div class="absolute inset-x-0 top-[calc(theme(spacing.16)+0.75rem)] z-10">
                <div class="mx-auto max-w-3xl px-4 sm:px-6">
                    <Link
                        :href="`/club/${club.slug}`"
                        class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-white/60 transition-colors hover:bg-white/10 hover:text-white"
                    >
                        <ArrowLeft class="size-4" />
                        <span class="max-w-[12rem] truncate">Volver a {{ club.name }}</span>
                    </Link>
                </div>
            </div>
        </section>

        <!-- Identity block: logo + name, overlapping cover -->
        <div class="mx-auto max-w-3xl px-4 sm:px-6">
            <div class="relative -mt-14 flex flex-col items-center gap-3 text-center sm:-mt-16 sm:flex-row sm:items-end sm:gap-5 sm:text-left">
                <div
                    class="relative z-10 shrink-0 rounded-full bg-background p-1.5 shadow-2xl ring-1 ring-border"
                    :style="{ boxShadow: `0 16px 40px -12px ${team.color}80` }"
                >
                    <img
                        v-if="team.logo_url"
                        :src="team.logo_url"
                        class="block size-24 rounded-full object-cover sm:size-28"
                        :alt="`Escudo de ${team.name}`"
                    >
                    <span
                        v-else
                        class="flex size-24 items-center justify-center rounded-full text-3xl font-black tracking-tight text-white sm:size-28 sm:text-4xl"
                        :style="{ background: `linear-gradient(135deg, ${team.color}, ${team.color}bb)` }"
                    >{{ team.name.charAt(0).toUpperCase() }}</span>
                </div>

                <div class="min-w-0 flex-1 sm:pb-2">
                    <Link :href="`/club/${club.slug}`" class="mb-2 inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] text-primary/90 transition-colors hover:text-primary">
                        <Shield class="size-3.5" />
                        {{ club.name }}
                    </Link>
                    <h1 class="truncate text-3xl font-black uppercase tracking-tight sm:text-4xl">{{ team.name }}</h1>
                    <div class="mt-2 flex flex-wrap items-center justify-center gap-2 text-xs sm:justify-start">
                        <span class="inline-flex items-center gap-1.5 rounded-md border border-border bg-muted/60 px-2 py-1 font-medium text-muted-foreground">
                            <Calendar class="size-3.5" />
                            {{ team.season.name }}
                        </span>
                        <span
                            v-if="team.season.is_active"
                            class="inline-flex items-center gap-1.5 rounded-md border border-emerald-500/40 bg-emerald-500/10 px-2 py-1 text-[10px] font-semibold uppercase tracking-wider text-emerald-500"
                        >
                            <span class="relative flex size-1.5">
                                <span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-500 opacity-75" />
                                <span class="relative inline-flex size-1.5 rounded-full bg-emerald-500" />
                            </span>
                            En curso
                        </span>
                    </div>
                </div>
            </div>

            <p v-if="team.bio" class="mt-4 text-center text-sm leading-relaxed text-muted-foreground sm:text-left">{{ team.bio }}</p>
        </div>

        <div class="mx-auto max-w-3xl px-4 pb-12 sm:px-6">
            <!-- Coach / Captain -->
            <div v-if="team.coach || team.captain" class="mt-8">
                <h2 class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-muted-foreground">Cuerpo técnico</h2>
                <div class="flex flex-wrap gap-3">
                    <div v-if="team.coach" class="flex items-center gap-3 rounded-xl border border-border bg-card px-3 py-2">
                        <UserAvatar :src="team.coach.photo_url" :name="team.coach.name" class="size-10" />
                        <div>
                            <p class="text-sm font-semibold">{{ team.coach.name }}</p>
                            <p class="text-xs text-muted-foreground">Director Técnico</p>
                        </div>
                    </div>
                    <div v-if="team.captain" class="flex items-center gap-3 rounded-xl border border-border bg-card px-3 py-2">
                        <UserAvatar :src="team.captain.photo_url" :name="team.captain.name" class="size-10" />
                        <div>
                            <p class="text-sm font-semibold">{{ team.captain.name }}</p>
                            <p class="text-xs text-muted-foreground">Capitán</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats row -->
            <div class="mt-6 grid grid-cols-4 gap-2 rounded-2xl border border-border bg-card p-3 sm:grid-cols-8">
                <div class="text-center">
                    <p class="font-mono text-xl font-extrabold tabular-nums">{{ stats.PJ }}</p>
                    <p class="mt-0.5 text-[10px] font-bold uppercase tracking-wider text-muted-foreground">PJ</p>
                </div>
                <div class="text-center">
                    <p class="font-mono text-xl font-extrabold tabular-nums text-emerald-500">{{ stats.G }}</p>
                    <p class="mt-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-500/80">G</p>
                </div>
                <div class="text-center">
                    <p class="font-mono text-xl font-extrabold tabular-nums text-amber-500">{{ stats.E }}</p>
                    <p class="mt-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-500/80">E</p>
                </div>
                <div class="text-center">
                    <p class="font-mono text-xl font-extrabold tabular-nums text-rose-500">{{ stats.P }}</p>
                    <p class="mt-0.5 text-[10px] font-bold uppercase tracking-wider text-rose-500/80">P</p>
                </div>
                <div class="text-center">
                    <p class="font-mono text-xl font-extrabold tabular-nums">{{ stats.GF }}</p>
                    <p class="mt-0.5 text-[10px] font-bold uppercase tracking-wider text-muted-foreground">GF</p>
                </div>
                <div class="text-center">
                    <p class="font-mono text-xl font-extrabold tabular-nums">{{ stats.GC }}</p>
                    <p class="mt-0.5 text-[10px] font-bold uppercase tracking-wider text-muted-foreground">GC</p>
                </div>
                <div class="text-center">
                    <p class="font-mono text-xl font-extrabold tabular-nums">{{ stats.DG > 0 ? '+' : '' }}{{ stats.DG }}</p>
                    <p class="mt-0.5 text-[10px] font-bold uppercase tracking-wider text-muted-foreground">DG</p>
                </div>
                <div class="rounded-lg bg-primary/10 text-center ring-1 ring-primary/30">
                    <p class="font-mono text-xl font-black tabular-nums text-primary">{{ stats.Pts }}</p>
                    <p class="mt-0.5 text-[10px] font-bold uppercase tracking-wider text-primary/80">Pts</p>
                </div>
            </div>

            <StandingsLegend />

            <!-- Plantilla -->
            <div class="mt-10">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="flex items-center gap-2 text-xl font-bold sm:text-2xl">
                        <Users class="size-5 text-primary" />
                        Plantilla
                        <span class="font-mono text-sm font-normal text-muted-foreground">· {{ team.players_count }}</span>
                    </h2>
                </div>
                <div v-if="team.players.length === 0" class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                    Sin jugadores registrados aún.
                </div>
                <div v-else class="grid gap-2 sm:grid-cols-2">
                    <Link
                        v-for="p in team.players"
                        :key="p.id"
                        :href="playerHref(p.ulid)"
                        class="group flex items-center gap-3 rounded-xl border border-border bg-card p-3 transition-all hover:border-primary/50 hover:shadow-md"
                    >
                        <UserAvatar :src="p.photo_url" :name="p.name" class="size-10" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold group-hover:text-primary">{{ p.name }}</p>
                            <div class="mt-0.5 flex items-center gap-2">
                                <Badge v-if="p.position" variant="outline" class="text-[10px]">{{ p.position }}</Badge>
                                <span v-if="p.jersey_number" class="font-mono text-xs text-muted-foreground">#{{ p.jersey_number }}</span>
                            </div>
                        </div>
                        <div
                            class="flex size-8 shrink-0 items-center justify-center rounded-lg text-xs font-black text-white"
                            :style="{ background: `linear-gradient(135deg, ${team.color}, ${team.color}cc)` }"
                        >
                            {{ p.jersey_number ?? '—' }}
                        </div>
                    </Link>
                </div>
            </div>

            <!-- Últimos partidos -->
            <div class="mt-10">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold sm:text-2xl">
                    <Trophy class="size-5 text-primary" />
                    Últimos partidos
                </h2>
                <div v-if="presentedMatches.length === 0" class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                    Sin partidos jugados aún.
                </div>
                <ul v-else class="space-y-2">
                    <li v-for="m in presentedMatches" :key="m.ulid">
                        <component
                            :is="matchHref(m) ? Link : 'div'"
                            :href="matchHref(m) ?? undefined"
                            class="group flex items-center gap-3 rounded-xl border border-border bg-card p-3 transition"
                            :class="matchHref(m) ? 'cursor-pointer hover:border-primary/50 hover:bg-accent/40 active:scale-[0.99]' : ''"
                        >
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-lg text-sm font-black"
                                :class="resultStyles[m.result].cls"
                                :title="resultStyles[m.result].label"
                            >
                                {{ resultStyles[m.result].letter }}
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 text-sm font-semibold">
                                    <span class="shrink-0 text-muted-foreground">vs</span>
                                    <span class="truncate">{{ m.opponentName }}</span>
                                </div>
                                <div class="mt-0.5 flex items-center gap-2 text-[11px] text-muted-foreground">
                                    <span>{{ formatDate(m.date) }}</span>
                                    <span v-if="m.isFriendly" class="rounded border border-amber-400/40 bg-amber-400/10 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wider text-amber-600">Amistoso</span>
                                    <span v-else class="text-[10px] uppercase tracking-wider">{{ m.isHome ? 'Local' : 'Visitante' }}</span>
                                </div>
                            </div>

                            <div
                                v-if="m.myScore !== null && m.theirScore !== null"
                                class="flex shrink-0 items-baseline gap-1 rounded-md border border-border bg-background px-2.5 py-1 tabular-nums"
                            >
                                <span
                                    class="text-base font-bold"
                                    :class="{ 'text-emerald-500': m.result === 'W', 'text-rose-500': m.result === 'L' }"
                                >{{ m.myScore }}</span>
                                <span class="text-xs text-muted-foreground">-</span>
                                <span class="text-base font-bold text-muted-foreground">{{ m.theirScore }}</span>
                            </div>
                            <span v-else class="shrink-0 text-[10px] uppercase tracking-wider text-muted-foreground">{{ m.status }}</span>

                            <ChevronRight v-if="matchHref(m)" class="size-4 shrink-0 text-muted-foreground/40 transition group-hover:translate-x-0.5 group-hover:text-primary" />
                        </component>
                    </li>
                </ul>
            </div>
        </div>

        <!-- CTA -->
        <section class="relative overflow-hidden bg-gradient-to-br from-slate-950 via-emerald-950 to-slate-950 py-16 sm:py-20">
            <div class="pointer-events-none absolute -top-24 left-1/2 size-96 -translate-x-1/2 rounded-full bg-emerald-500/20 blur-3xl" />
            <div class="relative mx-auto max-w-2xl px-4 text-center sm:px-6">
                <h2 class="mb-3 text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
                    ¿Y tu grupo de fútbol?
                </h2>
                <p class="mb-8 text-base text-white/70">
                    Organiza tus partidos gratis. En 2 minutos.
                </p>
                <Link
                    href="/start"
                    class="gradient-primary-bg inline-flex items-center gap-2 rounded-xl px-8 py-3.5 text-base font-semibold text-white shadow-xl transition-opacity hover:opacity-90"
                >
                    Empezar gratis
                    <ChevronRight class="size-5" />
                </Link>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-border bg-background py-8">
            <div class="mx-auto flex max-w-5xl flex-col items-center gap-3 px-4 text-center text-sm text-muted-foreground sm:px-6">
                <AppLogo />
                <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1">
                    <Link :href="`/club/${club.slug}`" class="hover:text-foreground">{{ club.name }}</Link>
                    <span class="text-muted-foreground/40">·</span>
                    <Link href="/news" class="hover:text-foreground">Noticias</Link>
                    <span class="text-muted-foreground/40">·</span>
                    <Link href="/terms" class="hover:text-foreground">Términos</Link>
                </div>
                <p class="text-xs">&copy; {{ new Date().getFullYear() }} Grandes del Fútbol</p>
            </div>
        </footer>
    </div>
</template>
