<script setup lang="ts">
defineProps<{
    src: string;
}>();

const emit = defineEmits<{
    play: [];
    pause: [];
}>();
</script>

<template>
    <div class="aspect-video w-full overflow-hidden rounded-xl border border-border bg-black">
        <video
            :src="src"
            class="h-full w-full"
            controls
            preload="metadata"
            playsinline
            @play="emit('play')"
            @pause="emit('pause')"
            @ended="emit('pause')"
        />
    </div>
</template>
