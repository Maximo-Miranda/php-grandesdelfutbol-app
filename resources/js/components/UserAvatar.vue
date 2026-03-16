<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

withDefaults(defineProps<{
    src?: string | null;
    name?: string;
    class?: HTMLAttributes['class'];
}>(), {
    src: null,
    name: '?',
});
</script>

<template>
    <Avatar :class="$props.class">
        <AvatarImage v-if="src" :src="src" :alt="name" />
        <AvatarFallback class="text-sm font-bold">{{ name.charAt(0).toUpperCase() }}</AvatarFallback>
    </Avatar>
</template>
