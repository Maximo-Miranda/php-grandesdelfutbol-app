<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Clock, Layers, MessageCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import NewsBookmarkButton from '@/components/news/NewsBookmarkButton.vue';
import NewsImageCarousel from '@/components/news/NewsImageCarousel.vue';
import NewsLikeButton from '@/components/news/NewsLikeButton.vue';
import NewsShareButton from '@/components/news/NewsShareButton.vue';
import { Badge } from '@/components/ui/badge';
import { buildShareUrl, formatTimeAgo } from '@/lib/utils';
import type { NewsArticle } from '@/types';

const props = defineProps<{
    article: NewsArticle;
    storySourceCount?: number;
}>();

const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth?.user);

const timeAgo = computed(() => formatTimeAgo(props.article.published_at));
const articleUrl = computed(() => `/news/${props.article.slug}`);

const shareUrl = computed(() => buildShareUrl(articleUrl.value));

const images = computed<string[]>(() => {
    const gallery = props.article.image_urls ?? [];

    if (gallery.length > 0) {
        return gallery;
    }

    return props.article.image_url ? [props.article.image_url] : [];
});

function openArticle() {
    router.visit(articleUrl.value);
}
</script>

<template>
    <article class="group overflow-hidden rounded-xl border border-border bg-card shadow-sm transition-all hover:border-primary/50 hover:shadow-md">
        <!-- Header: source + time -->
        <header class="flex items-center justify-between gap-2 px-4 py-3">
            <div class="flex min-w-0 items-center gap-2">
                <span v-if="article.source" class="truncate text-xs font-bold uppercase tracking-wide text-primary">
                    {{ article.source.name }}
                </span>
                <Badge
                    v-if="article.is_breaking"
                    class="shrink-0 bg-destructive text-[10px] font-bold uppercase tracking-wide text-destructive-foreground"
                >
                    Urgente
                </Badge>
            </div>
            <span class="flex shrink-0 items-center gap-1 text-xs text-muted-foreground">
                <Clock class="size-3" />
                {{ timeAgo }}
            </span>
        </header>

        <!-- Image(s) — clickable but not wrapped in Link to avoid carousel button conflicts -->
        <div v-if="images.length > 0" class="relative cursor-pointer" @click="openArticle">
            <NewsImageCarousel
                :images="images"
                :alt="article.title"
                aspect-ratio="video"
                class="rounded-none"
            />
        </div>

        <!-- Action row (Instagram-style: social left, save right) -->
        <div v-if="isAuthenticated" class="flex items-center justify-between px-4 pt-3">
            <div class="flex items-center gap-4">
                <NewsLikeButton
                    :article-slug="article.slug"
                    :is-liked="article.is_liked ?? false"
                    :likes-count="article.likes_count ?? 0"
                />
                <Link
                    :href="articleUrl"
                    class="inline-flex h-5 items-center gap-1.5 leading-none text-foreground transition-colors hover:text-primary"
                    aria-label="Ver comentarios"
                >
                    <MessageCircle class="size-5 shrink-0" />
                    <span v-if="(article.comments_count ?? 0) > 0" class="text-sm font-medium leading-none tabular-nums">
                        {{ article.comments_count }}
                    </span>
                </Link>
                <NewsShareButton
                    :article-slug="article.slug"
                    :article-title="article.title"
                    :share-url="shareUrl"
                />
            </div>
            <NewsBookmarkButton
                :article-slug="article.slug"
                :is-bookmarked="article.is_bookmarked ?? false"
            />
        </div>

        <!-- Body -->
        <div class="px-4 py-3">
            <Link :href="articleUrl" cache-for="5m" class="block">
                <h3 class="line-clamp-2 text-base font-bold leading-tight text-foreground group-hover:text-primary">
                    {{ article.title }}
                </h3>

                <p v-if="article.snippet" class="mt-2 line-clamp-2 text-sm leading-snug text-muted-foreground">
                    {{ article.snippet }}
                </p>
            </Link>

            <div v-if="storySourceCount && storySourceCount > 1" class="mt-3 flex items-center gap-1.5 text-xs font-medium text-primary">
                <Layers class="size-3.5" />
                {{ storySourceCount }} fuentes cubriendo esta historia
            </div>
        </div>
    </article>
</template>
