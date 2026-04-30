<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Award, Goal, Hand, Shield, ShieldCheck, Square, Target } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import { buildCanonicalUrl, truncateForMeta } from '@/lib/utils';

type PlayerStats = {
    goals: number;
    assists: number;
    matches_played: number;
    yellow_cards: number;
    red_cards: number;
    saves: number;
};

type PublicPlayer = {
    ulid: string;
    name: string;
    photo_url: string | null;
    jersey_number: number | null;
    position: string | null;
    position_label: string | null;
    is_active: boolean;
    stats: PlayerStats;
};

type PublicProfile = {
    nickname: string | null;
    nationality: string | null;
    preferred_position: string | null;
    bio: string | null;
} | null;

type PublicClub = {
    ulid: string;
    slug: string;
    name: string;
    logo_url: string | null;
};

type PublicTeam = {
    ulid: string;
    name: string;
    color: string;
    logo_url: string | null;
};

const props = defineProps<{
    player: PublicPlayer;
    profile: PublicProfile;
    club: PublicClub;
    teams: PublicTeam[];
    appUrl: string;
}>();

const canonicalUrl = computed(() => buildCanonicalUrl(props.appUrl, `/player/${props.player.ulid}`));

const seoTitle = computed(() => `${props.player.name} — ${props.club.name}`);

const seoDescription = computed(() => {
    if (props.profile?.bio && props.profile.bio.trim().length > 0) {
        return truncateForMeta(props.profile.bio);
    }

    const role = props.player.position_label ?? 'jugador';
    return `${props.player.name} (${role}) en ${props.club.name}: ${props.player.stats.goals} goles, ${props.player.stats.assists} asistencias en ${props.player.stats.matches_played} partidos.`;
});

const ogImage = computed(() => props.player.photo_url ?? props.club.logo_url ?? buildCanonicalUrl(props.appUrl, '/pwa-512x512.png'));

const jsonLd = computed(() =>
    JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'Person',
        name: props.player.name,
        url: canonicalUrl.value,
        image: props.player.photo_url ?? undefined,
        nationality: props.profile?.nationality ?? undefined,
        jobTitle: props.player.position_label ?? 'Jugador de fútbol',
        memberOf: {
            '@type': 'SportsTeam',
            name: props.club.name,
            url: buildCanonicalUrl(props.appUrl, `/club/${props.club.slug}`),
        },
    }),
);

const statTiles = computed(() => [
    { label: 'Goles', value: props.player.stats.goals, icon: Goal, tone: 'emerald' as const },
    { label: 'Asistencias', value: props.player.stats.assists, icon: Target, tone: 'sky' as const },
    { label: 'Partidos', value: props.player.stats.matches_played, icon: Award, tone: 'amber' as const },
    { label: 'Atajadas', value: props.player.stats.saves, icon: Hand, tone: 'violet' as const },
    { label: 'Amarillas', value: props.player.stats.yellow_cards, icon: Square, tone: 'yellow' as const },
    { label: 'Rojas', value: props.player.stats.red_cards, icon: Square, tone: 'rose' as const },
]);

const toneClasses: Record<'emerald' | 'sky' | 'amber' | 'violet' | 'yellow' | 'rose', string> = {
    emerald: 'text-emerald-500',
    sky: 'text-sky-500',
    amber: 'text-amber-500',
    violet: 'text-violet-500',
    yellow: 'text-yellow-500',
    rose: 'text-rose-500',
};

const page = usePage();

const fromTeamUlid = computed(() => {
    const from = new URL(page.url ?? '/', 'http://x').searchParams.get('from');
    return from?.startsWith('team:') ? from.slice(5) : null;
});

const backHref = computed(() =>
    fromTeamUlid.value ? `/team/${fromTeamUlid.value}` : `/club/${props.club.slug}`,
);

const backLabel = computed(() =>
    fromTeamUlid.value ? 'Volver al equipo' : `Volver a ${props.club.name}`,
);

const playerInitial = computed(() => props.player.name.trim().charAt(0).toUpperCase() || '?');
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

        <section class="relative overflow-hidden bg-gradient-to-br from-emerald-950 via-slate-900 to-slate-950 pt-20 pb-12 sm:pt-24 sm:pb-16">
            <div
                class="pointer-events-none absolute inset-0 opacity-[0.07]"
                style="background-image: repeating-linear-gradient(0deg, transparent 0, transparent 40px, white 40px, white 41px), repeating-linear-gradient(90deg, transparent 0, transparent 40px, white 40px, white 41px);"
            />
            <div class="pointer-events-none absolute -top-24 right-0 size-96 rounded-full bg-emerald-500/20 blur-3xl" />

            <div class="relative mx-auto max-w-4xl px-4 sm:px-6">
                <Link
                    :href="backHref"
                    class="mb-6 inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-white/60 transition-colors hover:bg-white/10 hover:text-white"
                >
                    <ArrowLeft class="size-4" />
                    <span class="max-w-[14rem] truncate">{{ backLabel }}</span>
                </Link>

                <div class="flex flex-col items-center gap-6 text-center sm:flex-row sm:items-end sm:gap-8 sm:text-left">
                    <div class="relative shrink-0">
                        <div class="flex size-32 items-center justify-center overflow-hidden rounded-3xl border-2 border-white/20 bg-gradient-to-br from-emerald-500/80 to-emerald-700 shadow-2xl sm:size-40">
                            <img
                                v-if="player.photo_url"
                                :src="player.photo_url"
                                :alt="player.name"
                                class="size-full object-cover"
                            >
                            <span v-else class="text-6xl font-black tracking-tight text-white drop-shadow sm:text-7xl">
                                {{ playerInitial }}
                            </span>
                        </div>
                        <div
                            v-if="player.jersey_number !== null"
                            class="absolute -bottom-2 -right-2 flex size-12 items-center justify-center rounded-2xl bg-emerald-500 text-xl font-black text-white shadow-lg ring-4 ring-slate-900"
                        >
                            #{{ player.jersey_number }}
                        </div>
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="mb-2 text-xs font-bold uppercase tracking-[0.25em] text-emerald-400/90">
                            <Link :href="`/club/${club.slug}`" class="hover:text-emerald-300">{{ club.name }}</Link>
                        </p>
                        <h1 class="mb-3 text-4xl font-extrabold tracking-tight text-white sm:text-5xl">{{ player.name }}</h1>
                        <div class="flex flex-wrap items-center justify-center gap-2 text-sm text-white/70 sm:justify-start">
                            <span v-if="player.position_label" class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1">
                                <ShieldCheck class="size-3.5" />
                                {{ player.position_label }}
                            </span>
                            <span v-if="profile?.nationality" class="rounded-full bg-white/10 px-3 py-1">
                                {{ profile.nationality }}
                            </span>
                            <span v-if="!player.is_active" class="rounded-full bg-rose-500/20 px-3 py-1 text-rose-300">
                                Inactivo
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-b border-border py-10 sm:py-14">
            <div class="mx-auto max-w-4xl px-4 sm:px-6">
                <div class="mb-6">
                    <p class="mb-1 text-xs font-bold uppercase tracking-[0.25em] text-primary">Tarjeta de jugador</p>
                    <h2 class="text-2xl font-bold sm:text-3xl">Estadísticas</h2>
                </div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div
                        v-for="stat in statTiles"
                        :key="stat.label"
                        class="rounded-2xl border border-border bg-card p-4 sm:p-5"
                    >
                        <div class="flex items-center justify-between">
                            <component :is="stat.icon" class="size-5" :class="toneClasses[stat.tone]" />
                            <span class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">{{ stat.label }}</span>
                        </div>
                        <p class="mt-3 font-mono text-3xl font-extrabold tabular-nums sm:text-4xl">{{ stat.value }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="profile?.bio" class="border-b border-border py-10 sm:py-14">
            <div class="mx-auto max-w-4xl px-4 sm:px-6">
                <h2 class="mb-3 text-xl font-bold sm:text-2xl">Sobre {{ profile.nickname ?? player.name }}</h2>
                <p class="text-base leading-relaxed text-muted-foreground">{{ profile.bio }}</p>
            </div>
        </section>

        <section v-if="teams.length > 0" class="border-b border-border py-10 sm:py-14">
            <div class="mx-auto max-w-4xl px-4 sm:px-6">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold sm:text-2xl">
                    <Shield class="size-5 text-primary" />
                    Equipos
                </h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <Link
                        v-for="team in teams"
                        :key="team.ulid"
                        :href="`/team/${team.ulid}`"
                        class="group flex items-center gap-3 rounded-xl border border-border bg-card p-3 transition-all hover:border-primary/50 hover:shadow-md"
                    >
                        <div
                            v-if="team.logo_url"
                            class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-muted ring-2 ring-white/10"
                        >
                            <img :src="team.logo_url" :alt="`Escudo de ${team.name}`" class="size-full object-cover" />
                        </div>
                        <div
                            v-else
                            class="flex size-12 shrink-0 items-center justify-center rounded-xl text-white shadow-inner ring-2 ring-white/10"
                            :style="{ background: `linear-gradient(135deg, ${team.color}, ${team.color}cc)` }"
                        >
                            <span class="text-base font-black tracking-tight drop-shadow">{{ team.name.charAt(0).toUpperCase() }}</span>
                        </div>
                        <p class="min-w-0 flex-1 truncate text-base font-semibold">{{ team.name }}</p>
                    </Link>
                </div>
            </div>
        </section>

        <footer class="border-t border-border bg-background py-8">
            <div class="mx-auto flex max-w-5xl flex-col items-center gap-3 px-4 text-center text-sm text-muted-foreground sm:px-6">
                <AppLogo />
                <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1">
                    <Link :href="`/club/${club.slug}`" class="hover:text-foreground">{{ club.name }}</Link>
                    <span class="text-muted-foreground/40">·</span>
                    <Link href="/terms" class="hover:text-foreground">Términos</Link>
                </div>
                <p class="text-xs">&copy; {{ new Date().getFullYear() }} Grandes del Fútbol</p>
            </div>
        </footer>
    </div>
</template>
