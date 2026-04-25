<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen, Clock, ExternalLink, Layers, Loader2, Sparkles, User2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import NewsBookmarkButton from '@/components/news/NewsBookmarkButton.vue';
import NewsComments from '@/components/news/NewsComments.vue';
import NewsImageCarousel from '@/components/news/NewsImageCarousel.vue';
import NewsLikeButton from '@/components/news/NewsLikeButton.vue';
import NewsShareButton from '@/components/news/NewsShareButton.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import SeoHead from '@/components/SeoHead.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useHistoryBack } from '@/composables/useHistoryBack';
import AppLayout from '@/layouts/AppLayout.vue';
import { buildShareUrl, formatTimeAgo, truncateForMeta } from '@/lib/utils';
import type { BreadcrumbItem, NewsArticle, NewsArticleComment } from '@/types';

type Props = {
    article: NewsArticle;
    relatedArticles: NewsArticle[];
    storySourceCount: number;
    isBookmarked: boolean;
    isLiked: boolean;
    likesCount: number;
    canSummarize: boolean;
    comments: NewsArticleComment[];
    commentsCount: number;
    readingMinutes: number;
};

const props = defineProps<Props>();
const page = usePage();

const isAuthenticated = computed(() => !!page.props.auth?.user);

const truncatedTitle = props.article.title.length > 50
    ? `${props.article.title.substring(0, 50)}...`
    : props.article.title;

const breadcrumbs: BreadcrumbItem[] = [
    { title: truncatedTitle, href: `/news/${props.article.slug}` },
];

const timeAgo = computed(() => formatTimeAgo(props.article.published_at));

const canonicalUrl = computed(() => `${window.location.origin}/news/${props.article.slug}`);

const seoDescription = computed(() =>
    truncateForMeta(props.article.ai_summary ?? props.article.snippet ?? props.article.full_content),
);

const seoTitle = computed(() => `${props.article.title} — Grandes del Fútbol`);

const ogImage = computed(() => {
    if (props.article.image_urls && props.article.image_urls.length > 0) {
        return props.article.image_urls[0];
    }

    return props.article.image_url ?? '';
});

const jsonLd = computed(() =>
    JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'NewsArticle',
        headline: props.article.title,
        description: seoDescription.value,
        datePublished: props.article.published_at,
        image: ogImage.value || undefined,
        mainEntityOfPage: canonicalUrl.value,
        publisher: {
            '@type': 'Organization',
            name: 'Grandes del Fútbol',
            logo: {
                '@type': 'ImageObject',
                url: `${window.location.origin}/pwa-512x512.png`,
            },
        },
        author: props.article.source?.name
            ? { '@type': 'Organization', name: props.article.source.name }
            : undefined,
    }),
);

const shareUrl = computed(() => buildShareUrl(`/news/${props.article.slug}`));

const images = computed<string[]>(() => {
    const gallery = props.article.image_urls ?? [];

    if (gallery.length > 0) {
        return gallery;
    }

    return props.article.image_url ? [props.article.image_url] : [];
});

const paragraphs = computed<string[]>(() => {
    const text = props.article.full_content ?? props.article.snippet ?? '';

    return text
        .split(/\n{2,}/)
        .map((p) => p.trim())
        .filter(Boolean);
});

const summaryLoading = ref(false);

const goBack = useHistoryBack('/news');

function generateSummary(): void {
    router.post(`/news/${props.article.slug}/summarize`, {}, {
        preserveScroll: true,
        onBefore: () => { summaryLoading.value = true; },
        onFinish: () => { summaryLoading.value = false; },
    });
}
</script>

<template>
    <SeoHead
        :title="seoTitle"
        :description="seoDescription"
        :canonical-url="canonicalUrl"
        :og-image="ogImage || null"
        og-type="article"
        :published-at="article.published_at"
        :publisher="article.source?.name ?? null"
    />

    <component :is="isAuthenticated ? AppLayout : 'div'" v-bind="isAuthenticated ? { breadcrumbs } : { class: 'min-h-screen bg-background' }">
        <!-- JSON-LD NewsArticle schema for search engines -->
        <!-- eslint-disable-next-line vue/no-v-text-v-html-on-component -->
        <component :is="'script'" type="application/ld+json" v-html="jsonLd" />

        <!-- Guest header -->
        <PublicHeader v-if="!isAuthenticated" />

        <article class="mx-auto max-w-3xl" :class="{ 'pt-16': !isAuthenticated }">
            <!-- Back -->
            <div class="px-4 pt-4 sm:pt-6">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    @click="goBack"
                >
                    <ArrowLeft class="size-3.5" />
                    Volver a noticias
                </button>
            </div>

            <!-- Source badge + urgent -->
            <div class="mt-4 flex items-center gap-2 px-4">
                <span v-if="article.source" class="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold uppercase tracking-wider text-primary">
                    {{ article.source.name }}
                </span>
                <Badge
                    v-if="article.is_breaking"
                    class="bg-destructive text-[10px] font-bold uppercase tracking-wider text-destructive-foreground"
                >
                    Urgente
                </Badge>
            </div>

            <!-- Hero title -->
            <header class="mt-3 px-4">
                <h1 class="text-2xl font-black leading-tight tracking-tight text-foreground sm:text-3xl">
                    {{ article.title }}
                </h1>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 text-xs text-muted-foreground">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                        <span v-if="article.author" class="flex items-center gap-1.5">
                            <User2 class="size-3.5" />
                            <span class="font-medium text-foreground/80">{{ article.author }}</span>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <Clock class="size-3.5" />
                            {{ timeAgo }}
                        </span>
                        <span class="flex items-center gap-1.5">
                            <BookOpen class="size-3.5" />
                            {{ readingMinutes }} min de lectura
                        </span>
                        <span v-if="storySourceCount > 1" class="flex items-center gap-1.5 text-primary">
                            <Layers class="size-3.5" />
                            {{ storySourceCount }} fuentes
                        </span>
                    </div>

                    <div v-if="isAuthenticated" class="flex items-center gap-4">
                        <NewsLikeButton
                            :article-slug="article.slug"
                            :is-liked="isLiked"
                            :likes-count="likesCount"
                        />
                        <NewsShareButton
                            :article-slug="article.slug"
                            :article-title="article.title"
                            :share-url="shareUrl"
                        />
                        <NewsBookmarkButton
                            :article-slug="article.slug"
                            :is-bookmarked="isBookmarked"
                        />
                    </div>
                </div>
            </header>

            <!-- Image carousel -->
            <div v-if="images.length > 0" class="mt-5 sm:px-4">
                <NewsImageCarousel
                    :images="images"
                    :alt="article.title"
                    aspect-ratio="video"
                    class="sm:rounded-xl"
                />
            </div>

            <!-- Body -->
            <div class="mt-6 px-4 space-y-4">
                <!-- Read original source CTA (prominent) -->
                <a
                    :href="article.original_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex items-center justify-between gap-3 rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 transition-colors hover:bg-primary/10"
                >
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-primary/80">Artículo completo</p>
                        <p class="mt-0.5 truncate text-sm font-semibold text-foreground">
                            Leer en {{ article.source?.name ?? 'la fuente original' }}
                        </p>
                    </div>
                    <ExternalLink class="size-4 shrink-0 text-primary" />
                </a>

                <!-- AI Summary -->
                <div v-if="article.ai_summary" class="rounded-xl border border-primary/20 bg-gradient-to-br from-primary/5 to-primary/10 p-4">
                    <div class="mb-2 flex items-center gap-1.5">
                        <Sparkles class="size-3.5 text-primary" />
                        <p class="text-[10px] font-bold uppercase tracking-wider text-primary">Resumen con IA</p>
                    </div>
                    <p class="whitespace-pre-wrap text-sm leading-relaxed text-foreground/90">{{ article.ai_summary }}</p>
                </div>

                <div v-else-if="isAuthenticated && canSummarize">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="summaryLoading"
                        class="gap-1.5"
                        @click="generateSummary"
                    >
                        <Loader2 v-if="summaryLoading" class="size-3.5 animate-spin" />
                        <Sparkles v-else class="size-3.5" />
                        {{ summaryLoading ? 'Generando resumen...' : 'Generar resumen con IA' }}
                    </Button>
                </div>

                <!-- Body text -->
                <div v-if="paragraphs.length > 0" class="space-y-4">
                    <p
                        v-for="(paragraph, idx) in paragraphs"
                        :key="idx"
                        class="text-sm leading-relaxed text-foreground/90 sm:text-base"
                        :class="{ 'text-base font-medium text-foreground sm:text-lg': idx === 0 }"
                    >
                        {{ paragraph }}
                    </p>
                </div>
            </div>

            <!-- Related articles (same story group) -->
            <section v-if="relatedArticles.length > 0" class="mt-10 px-4">
                <h2 class="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-foreground">
                    <Layers class="size-4 text-primary" />
                    Otras fuentes
                </h2>
                <div class="space-y-2">
                    <Link
                        v-for="related in relatedArticles"
                        :key="related.ulid"
                        :href="`/news/${related.slug}`"
                        class="block rounded-lg border border-border bg-card p-3 transition-colors hover:border-primary/40 hover:bg-accent/30"
                    >
                        <p class="line-clamp-2 text-sm font-semibold text-foreground">{{ related.title }}</p>
                        <p v-if="related.source" class="mt-1 text-xs font-medium text-muted-foreground">{{ related.source.name }}</p>
                    </Link>
                </div>
            </section>

            <!-- Comments -->
            <div class="px-4">
                <NewsComments
                    :comments="comments"
                    :comments-count="commentsCount"
                    :article-slug="article.slug"
                />
            </div>

            <!-- Guest CTA -->
            <div v-if="!isAuthenticated" class="mx-4 mt-10 mb-10 rounded-xl border border-primary/20 bg-gradient-to-br from-primary/5 to-primary/10 p-6 text-center">
                <p class="text-base font-bold text-foreground">Regístrate gratis</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Comenta, guarda y personaliza tu feed de noticias futboleras
                </p>
                <Link href="/start?mode=register" class="mt-4 inline-block">
                    <Button size="sm">Crear cuenta</Button>
                </Link>
            </div>
        </article>
    </component>
</template>
