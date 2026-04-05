<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Clock, Layers } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { formatTimeAgo } from '@/lib/utils';
import type { NewsArticle } from '@/types';

const props = defineProps<{
    article: NewsArticle;
    storySourceCount?: number;
}>();

const imageFailed = ref(false);
const timeAgo = computed(() => formatTimeAgo(props.article.published_at));
const articleUrl = computed(() => `/news/${props.article.slug}`);
</script>

<template>
    <Link :href="articleUrl" class="group block" cache-for="5m">
        <div class="flex gap-3 rounded-lg border border-border bg-card p-3 transition-colors hover:bg-accent/50">
            <div v-if="article.image_url && !imageFailed" class="relative h-24 w-24 shrink-0 overflow-hidden rounded-md">
                <img
                    :src="article.image_url"
                    :alt="article.title"
                    class="h-full w-full object-cover"
                    loading="lazy"
                    @error="imageFailed = true"
                />
                <Badge
                    v-if="article.is_breaking"
                    class="absolute left-1 top-1 bg-destructive text-destructive-foreground text-[10px] px-1 py-0"
                >
                    URGENTE
                </Badge>
            </div>

            <div class="flex min-w-0 flex-1 flex-col justify-between">
                <div>
                    <h3 class="line-clamp-2 text-sm font-semibold leading-tight text-foreground group-hover:text-primary">
                        {{ article.title }}
                    </h3>
                    <p v-if="article.snippet" class="mt-1 line-clamp-2 text-xs text-muted-foreground">
                        {{ article.snippet }}
                    </p>
                </div>

                <div class="mt-2 flex items-center gap-2 text-xs text-muted-foreground">
                    <span v-if="article.source" class="font-medium text-foreground/70">
                        {{ article.source.name }}
                    </span>
                    <span class="flex items-center gap-0.5">
                        <Clock class="size-3" />
                        {{ timeAgo }}
                    </span>
                    <span v-if="storySourceCount && storySourceCount > 1" class="flex items-center gap-0.5 text-primary">
                        <Layers class="size-3" />
                        {{ storySourceCount }} fuentes
                    </span>
                </div>
            </div>
        </div>
    </Link>
</template>
