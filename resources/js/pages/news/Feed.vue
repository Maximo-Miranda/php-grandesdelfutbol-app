<script setup lang="ts">
import { Head, InfiniteScroll, Link, router, usePage } from '@inertiajs/vue3';
import { Bookmark, Newspaper, Search, Settings2, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import NewsArticleCard from '@/components/news/NewsArticleCard.vue';
import NewsCategoryPills from '@/components/news/NewsCategoryPills.vue';
import NewsFeedSkeleton from '@/components/news/NewsFeedSkeleton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useNewsFeedDirty } from '@/composables/useNewsFeedDirty';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, NewsArticle } from '@/types';

type Props = {
    articles: { data: NewsArticle[] };
    currentCategory: string | null;
    search: string | null;
    hasPreferences: boolean;
};

const props = defineProps<Props>();
const page = usePage();

const isAuthenticated = computed(() => !!page.props.auth?.user);

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Noticias', href: '/news' }];

const searchQuery = ref(props.search ?? '');
let debounceTimer: ReturnType<typeof setTimeout>;

watch(searchQuery, (value) => {
    clearTimeout(debounceTimer);

    const isEmpty = !value.trim();

    debounceTimer = setTimeout(() => {
        router.visit('/news', {
            data: isEmpty ? {} : { search: value.trim() },
            preserveScroll: true,
            preserveState: true,
            only: ['articles', 'search'],
            reset: ['articles'],
        });
    }, isEmpty ? 0 : 400);
});

function clearSearch(): void {
    searchQuery.value = '';
}

const isSearching = computed(() => !!props.search);
const emptyMessage = computed(() => {
    if (isSearching.value) {
        return `No se encontraron noticias para "${props.search}".`;
    }

    return 'No hay noticias disponibles.';
});

const { consumeDirty } = useNewsFeedDirty();

function refreshIfDirty(): void {
    if (!consumeDirty()) {
        return;
    }

    router.reload({ only: ['articles'] });
}

onMounted(refreshIfDirty);

const offNavigate = router.on('navigate', (event) => {
    if (event.detail.page.component === 'news/Feed') {
        refreshIfDirty();
    }
});

onBeforeUnmount(() => offNavigate());
</script>

<template>
    <Head title="Noticias" />

    <component :is="isAuthenticated ? AppLayout : 'div'" v-bind="isAuthenticated ? { breadcrumbs } : { class: 'min-h-screen bg-background' }">
        <!-- Guest header -->
        <header v-if="!isAuthenticated" class="sticky top-0 z-40 border-b border-border bg-background/95 backdrop-blur-md">
            <div class="mx-auto flex h-14 max-w-2xl items-center justify-between px-4">
                <Link href="/" class="flex items-center gap-2">
                    <AppLogoIcon class="size-8 text-primary" />
                    <span class="text-sm font-bold tracking-tight">GDF Noticias</span>
                </Link>
                <Link href="/start">
                    <Button size="sm" variant="default">Iniciar sesión</Button>
                </Link>
            </div>
        </header>

        <component :is="isAuthenticated ? 'div' : 'main'" class="mx-auto max-w-2xl px-4 py-6">
            <div v-if="isAuthenticated" class="mb-4 flex items-center justify-between">
                <h1 class="text-lg font-bold">Noticias</h1>
                <div class="flex items-center gap-1">
                    <Link
                        href="/news/bookmarks"
                        aria-label="Ver noticias guardadas"
                        class="rounded-full p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    >
                        <Bookmark class="size-5" />
                    </Link>
                    <Link
                        href="/news/preferences"
                        aria-label="Personalizar feed"
                        class="rounded-full p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    >
                        <Settings2 class="size-5" />
                    </Link>
                </div>
            </div>

            <!-- Search bar -->
            <div class="relative mb-4">
                <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Buscar noticias..."
                    class="pl-9 pr-9"
                />
                <button
                    v-if="searchQuery"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    @click="clearSearch"
                >
                    <X class="size-4" />
                </button>
            </div>

            <NewsCategoryPills v-if="!isSearching" :current-category="currentCategory" :has-preferences="hasPreferences" class="mb-4" />

            <!-- Preferences hint (auth only) -->
            <div v-if="isAuthenticated && !hasPreferences && !isSearching" class="mb-4 rounded-lg border border-primary/20 bg-primary/5 p-3">
                <p class="text-sm text-muted-foreground">
                    <Link href="/news/preferences" class="font-medium text-primary hover:underline">
                        Personaliza tu feed
                    </Link>
                    para ver noticias de tus equipos y ligas favoritas.
                </p>
            </div>

            <InfiniteScroll data="articles" only-next preserve-url>
                <template #default>
                    <div class="space-y-3">
                        <NewsArticleCard
                            v-for="article in props.articles.data"
                            :key="article.ulid"
                            :article="article"
                        />
                    </div>
                </template>
                <template #loading>
                    <NewsFeedSkeleton class="mt-3" />
                </template>
            </InfiniteScroll>

            <div v-if="props.articles.data.length === 0" class="py-16 text-center">
                <Newspaper class="mx-auto size-12 text-muted-foreground/50" />
                <p class="mt-3 text-sm text-muted-foreground">{{ emptyMessage }}</p>
            </div>

            <!-- Guest CTA -->
            <div v-if="!isAuthenticated" class="mt-6 rounded-lg border border-primary/20 bg-primary/5 p-4 text-center">
                <p class="text-sm font-medium text-foreground">Regístrate para personalizar tu feed</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Elige tus equipos y ligas favoritas para ver noticias relevantes.
                </p>
                <Link href="/start?mode=register" class="mt-3 inline-block">
                    <Button size="sm">Crear cuenta gratis</Button>
                </Link>
            </div>
        </component>
    </component>
</template>
