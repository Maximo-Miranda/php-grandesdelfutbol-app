<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ChevronRight, Compass, Search, Shield, Trophy, Users, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import ClubShield from '@/components/ClubShield.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { buildCanonicalUrl } from '@/lib/utils';

type ClubCard = {
    ulid: string;
    slug: string;
    name: string;
    description: string | null;
    logo_url: string | null;
    completed_matches_count: number;
    upcoming_matches_count: number;
    players_count: number;
};

type Paginated<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
};

const props = defineProps<{
    clubs: Paginated<ClubCard>;
    search: string;
    appUrl: string;
}>();

const canonicalUrl = computed(() => buildCanonicalUrl(props.appUrl, '/clubes'));

const searchQuery = ref(props.search ?? '');
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(searchQuery, (value) => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    const trimmed = value.trim();

    debounceTimer = setTimeout(() => {
        router.visit('/clubes', {
            data: trimmed ? { q: trimmed } : {},
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ['clubs', 'search'],
        });
    }, trimmed ? 350 : 0);
});

function clearSearch(): void {
    searchQuery.value = '';
}
</script>

<template>
    <SeoHead
        title="Encuentra tu club — Grandes del Fútbol"
        description="Encuentra tu club de fútbol. Descubre partidos, equipos y resultados. O crea el tuyo gratis en 2 minutos."
        :canonical-url="canonicalUrl"
    />

    <div class="min-h-screen bg-background text-foreground">
        <PublicHeader />

        <!-- Hero -->
        <section class="relative overflow-hidden bg-gradient-to-br from-emerald-950 via-slate-900 to-slate-950 pt-20 pb-10 sm:pt-28 sm:pb-14">
            <div
                class="pointer-events-none absolute inset-0 opacity-[0.07]"
                style="background-image: repeating-linear-gradient(0deg, transparent 0, transparent 40px, white 40px, white 41px), repeating-linear-gradient(90deg, transparent 0, transparent 40px, white 40px, white 41px);"
            />
            <div class="pointer-events-none absolute -top-24 right-0 size-96 rounded-full bg-emerald-500/20 blur-3xl" />

            <div class="relative mx-auto max-w-3xl px-4 text-center sm:px-6">
                <Compass class="mx-auto mb-3 size-10 text-emerald-400" />
                <p class="mb-2 text-xs font-bold uppercase tracking-[0.25em] text-emerald-400/90">Explorar</p>
                <h1 class="mb-4 text-3xl font-extrabold tracking-tight text-white sm:text-5xl">
                    Encuentra tu club
                </h1>
                <p class="mx-auto max-w-xl text-sm text-white/70 sm:text-base">
                    Descubre partidos, equipos y resultados. O crea el tuyo en 2 minutos.
                </p>

                <!-- Search -->
                <div class="relative mx-auto mt-8 max-w-lg">
                    <Search class="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-white/50" />
                    <Input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Buscar por nombre del club..."
                        class="h-12 border-white/20 bg-white/5 pl-11 pr-11 text-white placeholder:text-white/40 focus-visible:ring-emerald-500/50"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        class="absolute right-3 top-1/2 flex size-7 -translate-y-1/2 items-center justify-center rounded-full text-white/60 transition-colors hover:bg-white/10 hover:text-white"
                        aria-label="Limpiar búsqueda"
                        @click="clearSearch"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </div>
        </section>

        <!-- Grid of clubs -->
        <section class="py-10 sm:py-14">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div v-if="clubs.data.length === 0" class="rounded-2xl border border-dashed border-border p-12 text-center">
                    <Compass class="mx-auto mb-3 size-10 text-muted-foreground/50" />
                    <p class="text-lg font-semibold">
                        <template v-if="search">Sin resultados para "{{ search }}"</template>
                        <template v-else>Aún no hay clubes públicos</template>
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        <template v-if="search">Probá con otro nombre o explorá todos los clubes.</template>
                        <template v-else>Creá el primero y sé parte de la comunidad.</template>
                    </p>
                    <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
                        <Button v-if="search" variant="outline" @click="clearSearch">Ver todos</Button>
                        <Link
                            href="/start"
                            class="gradient-primary-bg inline-flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold text-white shadow"
                        >
                            Crear mi club
                            <ChevronRight class="size-4" />
                        </Link>
                    </div>
                </div>

                <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="club in clubs.data"
                        :key="club.ulid"
                        :href="`/club/${club.slug}`"
                        class="group overflow-hidden rounded-2xl border border-border bg-card transition-all hover:-translate-y-0.5 hover:border-primary/50 hover:shadow-xl hover:shadow-primary/5"
                    >
                        <!-- Header with logo -->
                        <div class="flex items-center gap-4 border-b border-border/60 bg-gradient-to-br from-emerald-500/5 via-transparent to-transparent p-5">
                            <div
                                v-if="club.logo_url"
                                class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-muted ring-2 ring-white/10"
                            >
                                <img :src="club.logo_url" :alt="`Escudo de ${club.name}`" class="size-full object-cover" />
                            </div>
                            <div v-else class="shrink-0">
                                <ClubShield :name="club.name" :size="64" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="truncate text-lg font-bold leading-tight">{{ club.name }}</h3>
                                <p v-if="club.description" class="mt-1 line-clamp-2 text-xs text-muted-foreground">{{ club.description }}</p>
                                <p v-else class="mt-1 text-xs text-muted-foreground/60 italic">Sin descripción</p>
                            </div>
                        </div>

                        <!-- Stats footer -->
                        <div class="grid grid-cols-3 gap-px bg-border/40">
                            <div class="flex flex-col items-center gap-0.5 bg-card py-3">
                                <Users class="size-3.5 text-muted-foreground" />
                                <span class="font-mono text-sm font-bold">{{ club.players_count }}</span>
                                <span class="text-[9px] font-semibold uppercase tracking-wider text-muted-foreground">Plantel</span>
                            </div>
                            <div class="flex flex-col items-center gap-0.5 bg-card py-3">
                                <Trophy class="size-3.5 text-muted-foreground" />
                                <span class="font-mono text-sm font-bold">{{ club.completed_matches_count }}</span>
                                <span class="text-[9px] font-semibold uppercase tracking-wider text-muted-foreground">Jugados</span>
                            </div>
                            <div class="flex flex-col items-center gap-0.5 bg-card py-3">
                                <Shield class="size-3.5 text-muted-foreground" />
                                <span class="font-mono text-sm font-bold">{{ club.upcoming_matches_count }}</span>
                                <span class="text-[9px] font-semibold uppercase tracking-wider text-muted-foreground">Próximos</span>
                            </div>
                        </div>
                    </Link>
                </div>

                <!-- Pagination -->
                <div v-if="clubs.data.length > 0 && (clubs.prev_page_url || clubs.next_page_url)" class="mt-10 flex items-center justify-center gap-3">
                    <Link
                        v-if="clubs.prev_page_url"
                        :href="clubs.prev_page_url"
                        preserve-scroll
                        class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-card px-4 py-2 text-sm font-medium transition-colors hover:bg-accent"
                    >
                        <ChevronRight class="size-4 rotate-180" />
                        Anterior
                    </Link>
                    <Link
                        v-if="clubs.next_page_url"
                        :href="clubs.next_page_url"
                        preserve-scroll
                        class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-card px-4 py-2 text-sm font-medium transition-colors hover:bg-accent"
                    >
                        Siguiente
                        <ChevronRight class="size-4" />
                    </Link>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-border py-8">
            <div class="mx-auto flex max-w-5xl flex-col items-center gap-3 px-4 text-center text-sm text-muted-foreground sm:px-6">
                <AppLogo />
                <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1">
                    <Link href="/" class="hover:text-foreground">Inicio</Link>
                    <span class="text-muted-foreground/40">·</span>
                    <Link href="/terms" class="hover:text-foreground">Términos</Link>
                </div>
                <p class="text-xs">&copy; {{ new Date().getFullYear() }} Grandes del Fútbol</p>
            </div>
        </footer>
    </div>
</template>
