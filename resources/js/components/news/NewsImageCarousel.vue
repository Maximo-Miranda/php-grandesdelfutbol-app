<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    images: string[];
    alt?: string;
    aspectRatio?: 'square' | 'video';
}>();

const currentIndex = ref(0);
const failedImages = ref<Set<number>>(new Set());
const touchStartX = ref(0);

const visibleImages = computed(() =>
    props.images
        .map((url, index) => ({ url, index }))
        .filter(({ index }) => !failedImages.value.has(index)),
);

const hasMultipleImages = computed(() => visibleImages.value.length > 1);

const aspectClass = computed(() =>
    props.aspectRatio === 'square' ? 'aspect-square' : 'aspect-video',
);

function stop(event: Event): void {
    event.preventDefault();
    event.stopPropagation();
}

function goToIndex(index: number): void {
    const count = visibleImages.value.length;

    if (count === 0) {
        return;
    }

    currentIndex.value = ((index % count) + count) % count;
}

function prev(event: Event): void {
    stop(event);
    goToIndex(currentIndex.value - 1);
}

function next(event: Event): void {
    stop(event);
    goToIndex(currentIndex.value + 1);
}

function goTo(event: Event, index: number): void {
    stop(event);
    goToIndex(index);
}

function handleError(index: number): void {
    failedImages.value.add(index);

    if (currentIndex.value >= visibleImages.value.length) {
        currentIndex.value = Math.max(0, visibleImages.value.length - 1);
    }
}

function handleTouchStart(e: TouchEvent): void {
    touchStartX.value = e.changedTouches[0].screenX;
}

function handleTouchEnd(e: TouchEvent): void {
    const distance = touchStartX.value - e.changedTouches[0].screenX;

    if (Math.abs(distance) < 50) {
        return;
    }

    stop(e);
    goToIndex(currentIndex.value + (distance > 0 ? 1 : -1));
}
</script>

<template>
    <div v-if="visibleImages.length > 0" class="relative overflow-hidden rounded-lg bg-muted">
        <div
            class="relative w-full"
            :class="aspectClass"
            @touchstart="handleTouchStart"
            @touchend="handleTouchEnd"
        >
            <img
                v-for="({ url, index: originalIndex }, idx) in visibleImages"
                :key="originalIndex"
                :src="url"
                :alt="alt ?? `Imagen ${idx + 1}`"
                class="absolute inset-0 h-full w-full object-cover transition-opacity duration-300"
                :class="idx === currentIndex ? 'opacity-100' : 'opacity-0'"
                decoding="async"
                @error="handleError(originalIndex)"
            />
        </div>

        <template v-if="hasMultipleImages">
            <button
                type="button"
                class="absolute left-2 top-1/2 z-10 flex size-9 -translate-y-1/2 items-center justify-center rounded-full bg-black/60 text-white backdrop-blur-sm transition-all hover:bg-black/80 active:scale-95"
                aria-label="Imagen anterior"
                @mousedown.stop
                @click="prev"
            >
                <ChevronLeft class="size-5" />
            </button>
            <button
                type="button"
                class="absolute right-2 top-1/2 z-10 flex size-9 -translate-y-1/2 items-center justify-center rounded-full bg-black/60 text-white backdrop-blur-sm transition-all hover:bg-black/80 active:scale-95"
                aria-label="Imagen siguiente"
                @mousedown.stop
                @click="next"
            >
                <ChevronRight class="size-5" />
            </button>

            <div class="absolute bottom-3 left-0 right-0 z-10 flex justify-center gap-1.5">
                <button
                    v-for="(_, idx) in visibleImages"
                    :key="idx"
                    type="button"
                    class="size-2 rounded-full transition-all"
                    :class="idx === currentIndex ? 'w-5 bg-white' : 'bg-white/50 hover:bg-white/80'"
                    :aria-label="`Ir a imagen ${idx + 1}`"
                    @mousedown.stop
                    @click="(e) => goTo(e, idx)"
                />
            </div>

            <div class="absolute right-3 top-3 z-10 rounded-full bg-black/60 px-2 py-0.5 text-[10px] font-semibold text-white backdrop-blur-sm">
                {{ currentIndex + 1 }}/{{ visibleImages.length }}
            </div>
        </template>
    </div>
</template>
