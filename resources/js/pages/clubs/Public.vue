<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, Calendar, CalendarClock, ChevronRight, MapPin, Shield, Trophy, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import ClubShield from '@/components/ClubShield.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import { buildCanonicalUrl, truncateForMeta } from '@/lib/utils';

type PublicMatch = {
    id: number;
    ulid: string;
    title: string | null;
    scheduled_at: string;
    status: string;
    team_a_name: string | null;
    team_b_name: string | null;
    team_a_score?: number | null;
    team_b_score?: number | null;
    team_a_color?: string | null;
    team_b_color?: string | null;
    team_a_logo_url?: string | null;
    team_b_logo_url?: string | null;
    share_token?: string | null;
    field?: { id: number; name: string; venue?: { id: number; name: string; address?: string | null } | null } | null;
};

type PublicTeam = {
    id: number;
    ulid: string;
    name: string;
    color: string;
    logo_url: string | null;
};

type PublicClub = {
    ulid: string;
    slug: string;
    name: string;
    description: string | null;
    logo_url: string | null;
    completed_matches_count: number;
    upcoming_matches_count: number;
    players_count: number;
};

const props = defineProps<{
    club: PublicClub;
    nextMatches: PublicMatch[];
    recentMatches: PublicMatch[];
    teams: PublicTeam[];
    appUrl: string;
}>();

const canonicalUrl = computed(() => buildCanonicalUrl(props.appUrl, `/club/${props.club.slug}`));

const seoDescription = computed(() => {
    if (props.club.description && props.club.description.trim().length > 0) {
        return truncateForMeta(props.club.description);
    }

    return `${props.club.name}: ${props.club.players_count} jugadores, ${props.club.completed_matches_count} partidos jugados. Próximos partidos, resultados y equipos en Grandes del Fútbol.`;
});

const seoTitle = computed(() => `${props.club.name} — Grandes del Fútbol`);

const ogImage = computed(() => props.club.logo_url ?? buildCanonicalUrl(props.appUrl, '/pwa-512x512.png'));

const jsonLd = computed(() =>
    JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'SportsTeam',
        name: props.club.name,
        sport: 'Football',
        url: canonicalUrl.value,
        logo: props.club.logo_url ?? undefined,
        description: seoDescription.value,
        memberOf: {
            '@type': 'SportsOrganization',
            name: 'Grandes del Fútbol',
            url: props.appUrl,
        },
        numberOfEmployees: props.club.players_count,
    }),
);

const stats = computed(() => [
    { label: 'Plantel', value: props.club.players_count, icon: Users },
    { label: 'Jugados', value: props.club.completed_matches_count, icon: Trophy },
    { label: 'Próximos', value: props.club.upcoming_matches_count, icon: CalendarClock },
]);

function formatFullDate(iso: string): string {
    const date = new Date(iso);

    return date.toLocaleDateString('es-CO', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatDay(iso: string): { day: string; month: string; time: string } {
    const date = new Date(iso);

    return {
        day: date.toLocaleDateString('es-CO', { day: '2-digit' }),
        month: date.toLocaleDateString('es-CO', { month: 'short' }).replace('.', '').toUpperCase(),
        time: date.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' }),
    };
}

function matchUrl(match: PublicMatch): string | null {
    return match.share_token ? `/match/${match.share_token}` : null;
}

function teamName(name: string | null | undefined, fallback = 'Equipo'): string {
    const trimmed = name?.trim();

    return trimmed && trimmed.length > 0 ? trimmed : fallback;
}

function teamInitial(name: string | null | undefined, fallback = '?'): string {
    const trimmed = name?.trim();

    return trimmed && trimmed.length > 0 ? trimmed.charAt(0).toUpperCase() : fallback;
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
        <!-- JSON-LD SportsTeam schema -->
        <!-- eslint-disable-next-line vue/no-v-text-v-html-on-component -->
        <component :is="'script'" type="application/ld+json" v-html="jsonLd" />

        <PublicHeader />

        <!-- Hero: Champions-style -->
        <section class="relative overflow-hidden bg-gradient-to-br from-emerald-950 via-slate-900 to-slate-950 pt-20 pb-12 sm:pt-24 sm:pb-20">
            <!-- Field pattern overlay -->
            <div
                class="pointer-events-none absolute inset-0 opacity-[0.07]"
                style="background-image: repeating-linear-gradient(0deg, transparent 0, transparent 40px, white 40px, white 41px), repeating-linear-gradient(90deg, transparent 0, transparent 40px, white 40px, white 41px);"
            />
            <div class="pointer-events-none absolute -top-24 right-0 size-96 rounded-full bg-emerald-500/20 blur-3xl" />
            <div class="pointer-events-none absolute -bottom-24 left-0 size-96 rounded-full bg-emerald-600/10 blur-3xl" />

            <div class="relative mx-auto max-w-6xl px-4 sm:px-6">
                <!-- Back to explore -->
                <Link
                    href="/explorar"
                    class="mb-6 inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-white/60 transition-colors hover:bg-white/10 hover:text-white"
                >
                    <ArrowLeft class="size-4" />
                    Volver a clubes
                </Link>

                <div class="flex flex-col items-center gap-8 text-center sm:flex-row sm:items-center sm:gap-10 sm:text-left">
                    <!-- Shield -->
                    <div class="shrink-0">
                        <div v-if="club.logo_url" class="flex size-32 items-center justify-center overflow-hidden rounded-3xl border-2 border-white/20 bg-white/5 shadow-2xl sm:size-40">
                            <img :src="club.logo_url" :alt="`Escudo de ${club.name}`" class="size-full object-cover" />
                        </div>
                        <div v-else class="rounded-3xl bg-white/5 p-2 shadow-2xl ring-2 ring-white/10">
                            <ClubShield :name="club.name" :size="128" />
                        </div>
                    </div>

                    <!-- Title + description -->
                    <div class="min-w-0 flex-1">
                        <p class="mb-2 text-xs font-bold uppercase tracking-[0.25em] text-emerald-400/90">Club verificado</p>
                        <h1 class="mb-4 text-4xl font-extrabold tracking-tight text-white sm:text-5xl lg:text-6xl">
                            {{ club.name }}
                        </h1>
                        <p v-if="club.description" class="max-w-2xl text-base text-white/70 sm:text-lg">
                            {{ club.description }}
                        </p>
                    </div>
                </div>

                <!-- Stat bar -->
                <div class="mt-10 grid grid-cols-3 gap-px overflow-hidden rounded-2xl border border-white/10 bg-white/5 backdrop-blur-sm sm:mt-12">
                    <div
                        v-for="stat in stats"
                        :key="stat.label"
                        class="flex flex-col items-center gap-1.5 bg-slate-950/60 px-3 py-5 text-center sm:py-6"
                    >
                        <component :is="stat.icon" class="size-6 text-emerald-400 sm:size-7" />
                        <span class="font-mono text-3xl font-extrabold leading-none text-white tabular-nums sm:text-4xl lg:text-5xl">
                            {{ stat.value }}
                        </span>
                        <span class="text-[11px] font-semibold uppercase leading-tight tracking-wider text-white/60 sm:text-xs">
                            {{ stat.label }}
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Próximos partidos -->
        <section v-if="nextMatches.length > 0" class="border-b border-border py-12 sm:py-16">
            <div class="mx-auto max-w-5xl px-4 sm:px-6">
                <div class="mb-8 flex items-end justify-between gap-4">
                    <div>
                        <p class="mb-1 text-xs font-bold uppercase tracking-[0.25em] text-primary">Calendario</p>
                        <h2 class="flex items-center gap-2 text-2xl font-bold sm:text-3xl">
                            <Calendar class="size-6 text-primary" />
                            Próximos partidos
                        </h2>
                    </div>
                </div>

                <div class="grid gap-4">
                    <component
                        :is="matchUrl(match) ? Link : 'div'"
                        v-for="match in nextMatches"
                        :key="match.ulid"
                        :href="matchUrl(match) ?? undefined"
                        class="group relative overflow-hidden rounded-2xl border border-border bg-card transition-all"
                        :class="matchUrl(match) ? 'cursor-pointer hover:border-primary/60 hover:shadow-lg hover:shadow-primary/5' : ''"
                    >
                        <!-- Top strip: date + time -->
                        <div class="flex items-center justify-between gap-3 border-b border-border/60 bg-gradient-to-r from-emerald-500/10 via-transparent to-transparent px-4 py-2.5 sm:px-5">
                            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                                <Calendar class="size-3.5" />
                                <span>{{ formatDay(match.scheduled_at).day }} {{ formatDay(match.scheduled_at).month }}</span>
                                <span class="text-emerald-600/40 dark:text-emerald-400/40">·</span>
                                <span class="font-mono">{{ formatDay(match.scheduled_at).time }}</span>
                            </div>
                            <ChevronRight
                                v-if="matchUrl(match)"
                                class="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-1 group-hover:text-primary"
                            />
                        </div>

                        <!-- Teams face-off -->
                        <div class="flex items-center gap-3 px-4 py-5 sm:px-5">
                            <!-- Team A -->
                            <div class="flex min-w-0 flex-1 items-center gap-2.5 sm:gap-3">
                                <span
                                    v-if="match.team_a_logo_url"
                                    class="inline-flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-muted ring-2 ring-white/10 sm:size-12"
                                >
                                    <img :src="match.team_a_logo_url" :alt="`Escudo de ${teamName(match.team_a_name, 'Equipo A')}`" class="size-full object-cover" />
                                </span>
                                <span
                                    v-else
                                    class="inline-flex size-11 shrink-0 items-center justify-center rounded-full text-white shadow ring-2 ring-white/10 sm:size-12"
                                    :style="{ background: `linear-gradient(135deg, ${match.team_a_color ?? '#1a1a1a'}, ${match.team_a_color ?? '#1a1a1a'}cc)` }"
                                >
                                    <span class="text-base font-black tracking-tight drop-shadow sm:text-lg">{{ teamInitial(match.team_a_name, 'A') }}</span>
                                </span>
                                <p class="min-w-0 truncate text-sm font-bold leading-tight sm:text-base">{{ teamName(match.team_a_name, 'Equipo A') }}</p>
                            </div>

                            <!-- vs separator -->
                            <span class="shrink-0 rounded-md bg-muted px-2 py-1 text-[10px] font-bold uppercase tracking-widest text-muted-foreground sm:text-xs">vs</span>

                            <!-- Team B -->
                            <div class="flex min-w-0 flex-1 items-center justify-end gap-2.5 sm:gap-3">
                                <p class="min-w-0 truncate text-right text-sm font-bold leading-tight sm:text-base">{{ teamName(match.team_b_name, 'Equipo B') }}</p>
                                <span
                                    v-if="match.team_b_logo_url"
                                    class="inline-flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-muted ring-2 ring-white/10 sm:size-12"
                                >
                                    <img :src="match.team_b_logo_url" :alt="`Escudo de ${teamName(match.team_b_name, 'Equipo B')}`" class="size-full object-cover" />
                                </span>
                                <span
                                    v-else
                                    class="inline-flex size-11 shrink-0 items-center justify-center rounded-full text-white shadow ring-2 ring-white/10 sm:size-12"
                                    :style="{ background: `linear-gradient(135deg, ${match.team_b_color ?? '#facc15'}, ${match.team_b_color ?? '#facc15'}cc)` }"
                                >
                                    <span class="text-base font-black tracking-tight drop-shadow sm:text-lg">{{ teamInitial(match.team_b_name, 'B') }}</span>
                                </span>
                            </div>
                        </div>

                        <!-- Venue -->
                        <div v-if="match.field?.venue" class="flex items-center gap-1.5 border-t border-border/60 bg-muted/30 px-4 py-2 text-xs text-muted-foreground sm:px-5">
                            <MapPin class="size-3.5 shrink-0" />
                            <span class="truncate">
                                {{ match.field.venue.name }}<span v-if="match.field.name" class="text-muted-foreground/60"> · {{ match.field.name }}</span>
                            </span>
                        </div>
                    </component>
                </div>
            </div>
        </section>

        <!-- Últimos resultados — scoreboard style -->
        <section v-if="recentMatches.length > 0" class="border-b border-border bg-muted/30 py-12 sm:py-16">
            <div class="mx-auto max-w-5xl px-4 sm:px-6">
                <div class="mb-8">
                    <p class="mb-1 text-xs font-bold uppercase tracking-[0.25em] text-primary">Resultados</p>
                    <h2 class="flex items-center gap-2 text-2xl font-bold sm:text-3xl">
                        <Trophy class="size-6 text-primary" />
                        Últimos partidos jugados
                    </h2>
                </div>

                <div class="grid gap-4">
                    <component
                        :is="matchUrl(match) ? Link : 'div'"
                        v-for="match in recentMatches"
                        :key="match.ulid"
                        :href="matchUrl(match) ?? undefined"
                        class="group relative overflow-hidden rounded-2xl border border-border bg-card transition-all"
                        :class="matchUrl(match) ? 'cursor-pointer hover:border-primary/60 hover:shadow-lg hover:shadow-primary/5' : ''"
                    >
                        <!-- Team color gradient accents -->
                        <div
                            class="pointer-events-none absolute inset-y-0 left-0 w-1/2 opacity-70"
                            :style="{ background: `linear-gradient(90deg, ${match.team_a_color ?? '#1a1a1a'}22 0%, transparent 85%)` }"
                        />
                        <div
                            class="pointer-events-none absolute inset-y-0 right-0 w-1/2 opacity-70"
                            :style="{ background: `linear-gradient(270deg, ${match.team_b_color ?? '#facc15'}22 0%, transparent 85%)` }"
                        />

                        <div class="relative">
                            <!-- Final badge -->
                            <div class="flex items-center justify-center border-b border-border/40 bg-muted/20 px-4 py-1.5">
                                <span class="text-[10px] font-bold uppercase tracking-[0.25em] text-muted-foreground">Final</span>
                            </div>

                            <!-- Scoreboard: two rows, one per team, with score on the right (aligned) -->
                            <div class="divide-y divide-border/40 px-4 py-3 sm:px-6 sm:py-4">
                                <!-- Team A row -->
                                <div class="flex items-center gap-3 py-2.5 sm:gap-4">
                                    <span
                                        v-if="match.team_a_logo_url"
                                        class="inline-flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-muted ring-2 ring-white/10 sm:size-12"
                                    >
                                        <img :src="match.team_a_logo_url" :alt="`Escudo de ${teamName(match.team_a_name, 'Equipo A')}`" class="size-full object-cover" />
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex size-11 shrink-0 items-center justify-center rounded-full text-white shadow ring-2 ring-white/10 sm:size-12"
                                        :style="{ background: `linear-gradient(135deg, ${match.team_a_color ?? '#1a1a1a'}, ${match.team_a_color ?? '#1a1a1a'}cc)` }"
                                    >
                                        <span class="text-base font-black tracking-tight drop-shadow sm:text-lg">{{ teamInitial(match.team_a_name, 'A') }}</span>
                                    </span>
                                    <p
                                        class="min-w-0 flex-1 truncate text-sm font-bold leading-tight sm:text-base"
                                        :class="{
                                            'text-foreground': (match.team_a_score ?? 0) >= (match.team_b_score ?? 0),
                                            'text-muted-foreground': (match.team_a_score ?? 0) < (match.team_b_score ?? 0),
                                        }"
                                    >
                                        {{ teamName(match.team_a_name, 'Equipo A') }}
                                    </p>
                                    <span
                                        class="shrink-0 font-mono text-3xl font-black tabular-nums sm:text-4xl"
                                        :class="{
                                            'text-emerald-500': (match.team_a_score ?? 0) > (match.team_b_score ?? 0),
                                            'text-foreground': (match.team_a_score ?? 0) === (match.team_b_score ?? 0),
                                            'text-muted-foreground/70': (match.team_a_score ?? 0) < (match.team_b_score ?? 0),
                                        }"
                                    >{{ match.team_a_score ?? 0 }}</span>
                                </div>

                                <!-- Team B row -->
                                <div class="flex items-center gap-3 py-2.5 sm:gap-4">
                                    <span
                                        v-if="match.team_b_logo_url"
                                        class="inline-flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-muted ring-2 ring-white/10 sm:size-12"
                                    >
                                        <img :src="match.team_b_logo_url" :alt="`Escudo de ${teamName(match.team_b_name, 'Equipo B')}`" class="size-full object-cover" />
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex size-11 shrink-0 items-center justify-center rounded-full text-white shadow ring-2 ring-white/10 sm:size-12"
                                        :style="{ background: `linear-gradient(135deg, ${match.team_b_color ?? '#facc15'}, ${match.team_b_color ?? '#facc15'}cc)` }"
                                    >
                                        <span class="text-base font-black tracking-tight drop-shadow sm:text-lg">{{ teamInitial(match.team_b_name, 'B') }}</span>
                                    </span>
                                    <p
                                        class="min-w-0 flex-1 truncate text-sm font-bold leading-tight sm:text-base"
                                        :class="{
                                            'text-foreground': (match.team_b_score ?? 0) >= (match.team_a_score ?? 0),
                                            'text-muted-foreground': (match.team_b_score ?? 0) < (match.team_a_score ?? 0),
                                        }"
                                    >
                                        {{ teamName(match.team_b_name, 'Equipo B') }}
                                    </p>
                                    <span
                                        class="shrink-0 font-mono text-3xl font-black tabular-nums sm:text-4xl"
                                        :class="{
                                            'text-emerald-500': (match.team_b_score ?? 0) > (match.team_a_score ?? 0),
                                            'text-foreground': (match.team_b_score ?? 0) === (match.team_a_score ?? 0),
                                            'text-muted-foreground/70': (match.team_b_score ?? 0) < (match.team_a_score ?? 0),
                                        }"
                                    >{{ match.team_b_score ?? 0 }}</span>
                                </div>
                            </div>

                            <!-- Bottom strip: date + venue + chevron -->
                            <div class="flex items-center justify-between gap-2 border-t border-border/40 bg-muted/30 px-4 py-2 text-xs text-muted-foreground sm:px-6">
                                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-x-3 gap-y-0.5">
                                    <span class="inline-flex items-center gap-1">
                                        <Calendar class="size-3" />
                                        {{ formatFullDate(match.scheduled_at) }}
                                    </span>
                                    <span v-if="match.field?.venue" class="inline-flex items-center gap-1">
                                        <MapPin class="size-3" />
                                        {{ match.field.venue.name }}
                                    </span>
                                </div>
                                <span v-if="matchUrl(match)" class="hidden shrink-0 items-center gap-1 font-medium text-primary opacity-0 transition-opacity group-hover:opacity-100 sm:inline-flex">
                                    Ver partido <ChevronRight class="size-3" />
                                </span>
                            </div>
                        </div>
                    </component>
                </div>
            </div>
        </section>

        <!-- Equipos -->
        <section v-if="teams.length > 0" class="border-b border-border py-12 sm:py-16">
            <div class="mx-auto max-w-5xl px-4 sm:px-6">
                <div class="mb-8">
                    <p class="mb-1 text-xs font-bold uppercase tracking-[0.25em] text-primary">Plantel</p>
                    <h2 class="flex items-center gap-2 text-2xl font-bold sm:text-3xl">
                        <Shield class="size-6 text-primary" />
                        Equipos del club
                    </h2>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="team in teams"
                        :key="team.ulid"
                        :href="`/team/${team.ulid}`"
                        class="group flex items-center gap-3 overflow-hidden rounded-xl border border-border bg-card p-4 transition-all hover:border-primary/50 hover:shadow-md"
                    >
                        <div
                            v-if="team.logo_url"
                            class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-muted ring-2 ring-white/10 transition-transform group-hover:scale-105"
                        >
                            <img :src="team.logo_url" :alt="`Escudo de ${team.name}`" class="size-full object-cover" />
                        </div>
                        <div
                            v-else
                            class="flex size-12 shrink-0 items-center justify-center rounded-xl text-white shadow-inner ring-2 ring-white/10 transition-transform group-hover:scale-105"
                            :style="{ background: `linear-gradient(135deg, ${team.color}, ${team.color}cc)` }"
                        >
                            <span class="text-base font-black tracking-tight drop-shadow">{{ team.name.charAt(0).toUpperCase() }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-base font-semibold">{{ team.name }}</p>
                            <p class="mt-0.5 text-xs text-muted-foreground">Ver plantilla</p>
                        </div>
                        <ChevronRight class="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-1 group-hover:text-primary" />
                    </Link>
                </div>
            </div>
        </section>

        <!-- CTA — visitante con su propio grupo de amigos -->
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
                    <Link href="/news" class="hover:text-foreground">Noticias</Link>
                    <span class="text-muted-foreground/40">·</span>
                    <Link href="/terms" class="hover:text-foreground">Términos</Link>
                    <span class="text-muted-foreground/40">·</span>
                    <Link href="/privacy" class="hover:text-foreground">Privacidad</Link>
                </div>
                <p class="text-xs">&copy; {{ new Date().getFullYear() }} Grandes del Fútbol</p>
            </div>
        </footer>
    </div>
</template>
