<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Loader2, MessageCircle, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useInitials } from '@/composables/useInitials';
import { useNewsFeedDirty } from '@/composables/useNewsFeedDirty';
import { formatTimeAgo } from '@/lib/utils';
import type { NewsArticleComment } from '@/types';

const props = defineProps<{
    comments: NewsArticleComment[];
    commentsCount: number;
    articleSlug: string;
}>();

const page = usePage();
const { getInitials } = useInitials();
const { markDirty } = useNewsFeedDirty();

const currentUserId = computed(() => page.props.auth?.user?.id ?? null);
const isAuthenticated = computed(() => currentUserId.value !== null);

const form = useForm({
    body: '',
});

function submit() {
    if (! form.body.trim()) {
        return;
    }

    form.post(`/news/${props.articleSlug}/comments`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            markDirty();
        },
    });
}

function deleteComment(comment: NewsArticleComment) {
    router.delete(`/news/${props.articleSlug}/comments/${comment.ulid}`, {
        preserveScroll: true,
        onSuccess: () => markDirty(),
    });
}

function isOwnComment(comment: NewsArticleComment): boolean {
    return comment.user?.id === currentUserId.value;
}
</script>

<template>
    <section class="mt-8">
        <h2 class="mb-4 flex items-center gap-2 text-sm font-semibold text-foreground">
            <MessageCircle class="size-4" />
            Comentarios
            <span v-if="commentsCount > 0" class="text-muted-foreground">({{ commentsCount }})</span>
        </h2>

        <form v-if="isAuthenticated" class="mb-6" @submit.prevent="submit">
            <Textarea
                v-model="form.body"
                rows="3"
                maxlength="1000"
                placeholder="Escribe un comentario..."
                :disabled="form.processing"
            />
            <p v-if="form.errors.body" class="mt-1 text-xs text-destructive">
                {{ form.errors.body }}
            </p>
            <div class="mt-2 flex items-center justify-between">
                <p class="text-xs text-muted-foreground">{{ form.body.length }}/1000</p>
                <Button type="submit" size="sm" :disabled="form.processing || ! form.body.trim()" class="gap-1.5">
                    <Loader2 v-if="form.processing" class="size-3.5 animate-spin" />
                    Comentar
                </Button>
            </div>
        </form>

        <div v-else class="mb-6 rounded-lg border border-primary/20 bg-primary/5 p-3 text-center text-xs text-muted-foreground">
            <a href="/start" class="font-medium text-primary hover:underline">Inicia sesión</a>
            para comentar esta noticia
        </div>

        <div v-if="comments.length === 0" class="py-4 text-center text-xs text-muted-foreground">
            Sé el primero en comentar
        </div>

        <ul v-else class="space-y-4">
            <li v-for="comment in comments" :key="comment.ulid" class="flex gap-3">
                <Avatar class="size-8 shrink-0">
                    <AvatarFallback class="text-xs">
                        {{ getInitials(comment.user?.name ?? '?') }}
                    </AvatarFallback>
                </Avatar>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-medium text-foreground">
                            {{ comment.user?.name ?? 'Usuario' }}
                        </p>
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <span>{{ formatTimeAgo(comment.created_at) }}</span>
                            <button
                                v-if="isOwnComment(comment)"
                                type="button"
                                class="text-muted-foreground hover:text-destructive"
                                aria-label="Eliminar comentario"
                                @click="deleteComment(comment)"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </div>
                    </div>
                    <p class="mt-0.5 whitespace-pre-wrap text-sm text-foreground/90">
                        {{ comment.body }}
                    </p>
                </div>
            </li>
        </ul>
    </section>
</template>
