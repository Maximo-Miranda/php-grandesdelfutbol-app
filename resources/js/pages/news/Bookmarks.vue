<script setup lang="ts">
import { Head, InfiniteScroll, Link } from '@inertiajs/vue3';
import { BookmarkX } from 'lucide-vue-next';
import NewsArticleCard from '@/components/news/NewsArticleCard.vue';
import NewsFeedSkeleton from '@/components/news/NewsFeedSkeleton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, NewsArticle } from '@/types';

type Props = {
    articles: { data: NewsArticle[] };
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Noticias', href: '/news' },
    { title: 'Guardados', href: '/news/bookmarks' },
];
</script>

<template>
    <Head title="Noticias guardadas — Grandes del Fútbol">
        <meta head-key="description" name="description" content="Tus noticias de fútbol guardadas en Grandes del Fútbol." />
        <meta head-key="robots" name="robots" content="noindex, follow" />
    </Head>

    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="mx-auto max-w-2xl px-4 py-6">
            <header class="mb-4">
                <h1 class="text-lg font-bold text-foreground">Guardadas</h1>
                <p class="mt-1 text-xs text-muted-foreground">Las noticias que marcaste para leer después.</p>
            </header>

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
                <BookmarkX class="mx-auto size-12 text-muted-foreground/50" />
                <p class="mt-3 text-sm text-muted-foreground">Aún no has guardado ninguna noticia.</p>
                <Link href="/news" class="mt-4 inline-block text-sm font-medium text-primary hover:underline">
                    Explorar el feed
                </Link>
            </div>
        </main>
    </AppLayout>
</template>
