<script setup lang="ts">
import { computed  } from 'vue';
import type {Component} from 'vue';

type EventType = {
    value: string;
    label: string;
    icon: Component;
    color: string;
    bg: string;
};

const props = defineProps<{
    events: EventType[];
    disabled?: boolean;
    cols?: number;
    compact?: boolean;
}>();

const emit = defineEmits<{
    select: [eventType: string];
}>();

const colsClass = computed(() => {
    const map: Record<number, string> = {
        2: 'grid-cols-2',
        3: 'grid-cols-3',
        4: 'grid-cols-4',
        5: 'grid-cols-5',
        6: 'grid-cols-6',
    };
    return map[props.cols ?? 4] ?? 'grid-cols-4';
});
</script>

<template>
    <div class="grid gap-1.5" :class="colsClass">
        <button
            v-for="et in events"
            :key="et.value"
            :disabled="disabled"
            class="flex flex-col items-center justify-center gap-1 rounded-xl border transition-all active:scale-95 disabled:pointer-events-none disabled:opacity-30"
            :class="[et.bg, compact ? 'min-h-[44px] p-2' : 'min-h-[44px] gap-1.5 p-3 sm:p-3.5']"
            @click="emit('select', et.value)"
        >
            <component :is="et.icon" :class="[et.color, compact ? 'size-4' : 'size-6 sm:size-7']" />
            <span
                class="whitespace-pre-line text-center font-semibold leading-tight"
                :class="[et.color, compact ? 'text-[8px]' : 'text-[10px] sm:text-xs']"
            >{{ et.label }}</span>
        </button>
    </div>
</template>
