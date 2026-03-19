<script setup lang="ts">
import { getEventIcon, getEventIconColor } from '@/lib/match-events';

const props = defineProps<{
    eventType: string;
    class?: string;
}>();
</script>

<template>
    <component
        :is="getEventIcon(eventType)"
        :class="[props.class ?? 'size-5', getEventIconColor(eventType)]"
    />
</template>
