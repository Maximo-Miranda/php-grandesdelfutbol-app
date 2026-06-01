<script setup lang="ts">
defineProps<{
    src: string;
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
        />
    </div>
</template>
