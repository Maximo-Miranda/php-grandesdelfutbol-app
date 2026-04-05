<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Clock, ExternalLink, Layers, Loader2, Sparkles } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatTimeAgo } from '@/lib/utils';
import type { BreadcrumbItem, NewsArticle } from '@/types';

type Props = {
    article: NewsArticle;
    relatedArticles: NewsArticle[];
    storySourceCount: number;
    isBookmarked: boolean;
};

const props = defineProps<Props>();
const page = usePage();

const isAuthenticated = computed(() => !!page.props.auth?.user);

const breadcrumbs: BreadcrumbItem[] = [
    { title: props.article.title.length > 50 ? `${props.article.title.substring(0, 50)}...` : props.article.title, href: `/news/${props.article.slug}` },
];

function goBack() {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        router.visit('/news');
    }
}

const timeAgo = computed(() => formatTimeAgo(props.article.published_at));
const publishedDate = computed(() => formatDate(props.article.published_at, {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
}));

// Image fallback
const imageFailed = ref(false);

// AI Summary state
const summaryLoading = ref(false);

function generateSummary() {
    router.post(`/news/${props.article.slug}/summarize`, {}, {
        preserveScroll: true,
        onBefore: () => { summaryLoading.value = true; },
        onFinish: () => { summaryLoading.value = false; },
    });
}
</script>

<template>
    <Head :title="article.title" />

    <component :is="isAuthenticated ? AppLayout : 'div'" v-bind="isAuthenticated ? { breadcrumbs } : { class: 'min-h-screen bg-background' }">
        <!-- Guest header -->
        <header v-if="!isAuthenticated" class="sticky top-0 z-40 border-b border-border bg-background/95 backdrop-blur-md">
            <div class="mx-auto flex h-14 max-w-2xl items-center justify-between px-4">
                <Link href="/" class="flex items-center gap-2">
                    <AppLogo class="size-8" />
                </Link>
                <Link href="/start">
                    <Button size="sm" variant="default">Iniciar sesión</Button>
                </Link>
            </div>
        </header>

        <div class="mx-auto max-w-2xl px-4 py-6">
            <button
                class="mb-4 inline-flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                @click="goBack"
            >
                <ArrowLeft class="size-3.5" />
                Volver a noticias
            </button>

            <!-- Image -->
            <div v-if="article.image_url && !imageFailed" class="relative -mx-4 mb-4 overflow-hidden sm:mx-0 sm:rounded-lg">
                <img :src="article.image_url" :alt="article.title" class="w-full object-cover" @error="imageFailed = true" />
                <Badge
                    v-if="article.is_breaking"
                    class="absolute left-3 top-3 bg-destructive text-destructive-foreground"
                >
                    URGENTE
                </Badge>
            </div>

            <!-- Title -->
            <h1 class="text-xl font-bold leading-tight text-foreground">
                {{ article.title }}
            </h1>

            <!-- Meta -->
            <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                <span v-if="article.source" class="font-medium text-foreground/80">
                    {{ article.source.name }}
                </span>
                <span v-if="article.author">{{ article.author }}</span>
                <span class="flex items-center gap-1">
                    <Clock class="size-3" />
                    {{ timeAgo }}
                </span>
                <span v-if="storySourceCount > 1" class="flex items-center gap-1 text-primary">
                    <Layers class="size-3" />
                    {{ storySourceCount }} fuentes
                </span>
            </div>

            <p class="mt-1 text-xs text-muted-foreground">{{ publishedDate }}</p>

            <!-- Snippet -->
            <div v-if="article.snippet" class="mt-4">
                <p class="text-sm leading-relaxed text-foreground/90">
                    {{ article.snippet }}
                </p>
            </div>

            <!-- AI Summary -->
            <div v-if="article.ai_summary" class="mt-4 rounded-lg border border-primary/20 bg-primary/5 p-4">
                <p class="mb-1 text-xs font-medium text-primary">Resumen AI</p>
                <p class="text-sm leading-relaxed text-foreground/90">{{ article.ai_summary }}</p>
            </div>

            <!-- Generate summary button (auth only, no cached summary) -->
            <div v-else-if="isAuthenticated" class="mt-4">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="summaryLoading"
                    class="gap-1.5"
                    @click="generateSummary"
                >
                    <Loader2 v-if="summaryLoading" class="size-3.5 animate-spin" />
                    <Sparkles v-else class="size-3.5" />
                    {{ summaryLoading ? 'Generando resumen...' : 'Generar resumen' }}
                </Button>
            </div>

            <!-- Actions -->
            <div class="mt-6 flex items-center gap-3">
                <a
                    :href="article.original_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                >
                    <ExternalLink class="size-3.5" />
                    Ver en {{ article.source?.name ?? 'fuente' }}
                </a>
            </div>

            <!-- Related articles (same story group) -->
            <div v-if="relatedArticles.length > 0" class="mt-8">
                <h2 class="mb-3 text-sm font-semibold text-foreground">Otras fuentes</h2>
                <div class="space-y-2">
                    <Link
                        v-for="related in relatedArticles"
                        :key="related.ulid"
                        :href="`/news/${related.slug}`"
                        class="block rounded-lg border border-border p-3 transition-colors hover:bg-accent/50"
                    >
                        <p class="line-clamp-2 text-sm font-medium text-foreground">{{ related.title }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ related.source?.name }}</p>
                    </Link>
                </div>
            </div>

            <!-- Guest CTA -->
            <div v-if="!isAuthenticated" class="mt-8 rounded-lg border border-primary/20 bg-primary/5 p-4 text-center">
                <p class="text-sm font-medium text-foreground">Regístrate para guardar y compartir noticias</p>
                <Link href="/start?mode=register" class="mt-3 inline-block">
                    <Button size="sm">Crear cuenta gratis</Button>
                </Link>
            </div>
        </div>
    </component>
</template>
