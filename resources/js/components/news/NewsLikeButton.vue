<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Heart } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    articleSlug: string;
    isLiked: boolean;
    likesCount: number;
}>();

const liked = ref(props.isLiked);
const count = ref(props.likesCount);
const saving = ref(false);

watch(
    () => props.isLiked,
    (value) => {
        liked.value = value;
    },
);

watch(
    () => props.likesCount,
    (value) => {
        count.value = value;
    },
);

const formattedCount = computed(() => {
    if (count.value <= 0) {
        return '';
    }

    if (count.value < 1000) {
        return String(count.value);
    }

    return `${(count.value / 1000).toFixed(count.value < 10000 ? 1 : 0)}k`;
});

function toggle(): void {
    if (saving.value) {
        return;
    }

    const wasLiked = liked.value;
    liked.value = !wasLiked;
    count.value += wasLiked ? -1 : 1;
    saving.value = true;

    router.post(
        `/news/${props.articleSlug}/like`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                liked.value = wasLiked;
                count.value += wasLiked ? 1 : -1;
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
        :aria-label="liked ? 'Quitar me gusta' : 'Me gusta'"
        :aria-pressed="liked"
        :disabled="saving"
        class="inline-flex h-5 items-center gap-1.5 leading-none text-foreground transition-colors disabled:opacity-60"
        :class="liked ? 'text-red-500' : 'hover:text-red-500'"
        @click="toggle"
    >
        <Heart class="size-5 shrink-0" :fill="liked ? 'currentColor' : 'none'" />
        <span v-if="formattedCount" class="text-sm font-medium leading-none tabular-nums">{{ formattedCount }}</span>
    </button>
</template>
