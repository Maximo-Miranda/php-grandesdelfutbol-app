<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

type Size = 'sm' | 'md';

const props = withDefaults(defineProps<{
    target: string | number | Date;
    size?: Size;
}>(), {
    size: 'md',
});

const now = ref(Date.now());
let timer: ReturnType<typeof setInterval>;
onMounted(() => { timer = setInterval(() => { now.value = Date.now(); }, 1000); });
onBeforeUnmount(() => clearInterval(timer));

const segments = computed(() => {
    const diff = new Date(props.target).getTime() - now.value;
    if (diff <= 0) return [];

    const totalSecs = Math.floor(diff / 1000);
    const days = Math.floor(totalSecs / 86400);
    const hours = Math.floor((totalSecs % 86400) / 3600);
    const minutes = Math.floor((totalSecs % 3600) / 60);
    const seconds = totalSecs % 60;

    const result: { value: number; label: string }[] = [];
    if (days > 0) result.push({ value: days, label: 'Dias' });
    result.push({ value: hours, label: 'Hrs' });
    result.push({ value: minutes, label: 'Min' });
    result.push({ value: seconds, label: 'Seg' });
    return result;
});

const classes = computed(() => {
    const isSm = props.size === 'sm';
    return {
        box: isSm
            ? 'min-w-[40px] rounded-md border border-border bg-muted/50 px-2 py-1 text-center'
            : 'rounded-lg border border-border bg-muted/50 px-3 py-2 text-center',
        number: isSm ? 'text-base font-bold leading-none' : 'text-xl font-bold',
        label: isSm
            ? 'mt-0.5 text-[9px] uppercase text-muted-foreground'
            : 'text-[10px] uppercase text-muted-foreground',
        separator: isSm
            ? 'text-base font-bold text-muted-foreground'
            : 'text-xl font-bold text-muted-foreground',
    };
});

function pad(n: number): string {
    return String(n).padStart(2, '0');
}
</script>

<template>
    <div v-if="segments.length" class="flex items-center justify-center gap-1">
        <template v-for="(seg, i) in segments" :key="seg.label">
            <span v-if="i > 0" :class="classes.separator">:</span>
            <div :class="classes.box">
                <p :class="classes.number">{{ pad(seg.value) }}</p>
                <p :class="classes.label">{{ seg.label }}</p>
            </div>
        </template>
    </div>
</template>
