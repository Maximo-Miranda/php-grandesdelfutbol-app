<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Bookmark, BookmarkCheck } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    articleSlug: string;
    isBookmarked: boolean;
}>();

const bookmarked = ref(props.isBookmarked);
const saving = ref(false);

watch(
    () => props.isBookmarked,
    (value) => {
        bookmarked.value = value;
    },
);

function toggle(): void {
    if (saving.value) {
        return;
    }

    const previous = bookmarked.value;
    bookmarked.value = !previous;
    saving.value = true;

    router.post(
        `/news/${props.articleSlug}/bookmark`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                bookmarked.value = previous;
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}
</script>

<template>
    <button
        type="button"
        :aria-label="bookmarked ? 'Quitar de guardados' : 'Guardar noticia'"
        :aria-pressed="bookmarked"
        :disabled="saving"
        class="inline-flex h-5 items-center text-foreground transition-colors disabled:opacity-60"
        :class="bookmarked ? 'text-primary' : 'hover:text-primary'"
        @click="toggle"
    >
        <BookmarkCheck v-if="bookmarked" class="size-5 shrink-0" />
        <Bookmark v-else class="size-5 shrink-0" />
    </button>
</template>
