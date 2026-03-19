<script setup lang="ts">
import { Minus, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = withDefaults(defineProps<{
    startSecond: number;
    endSecond: number;
    maxSeconds: number;
    maxDuration?: number;
}>(), {
    maxDuration: 30,
});

const emit = defineEmits<{
    'update:startSecond': [value: number];
    'update:endSecond': [value: number];
}>();

const trackRef = ref<HTMLElement | null>(null);
const dragging = ref(false);

const MIN_CLIP = 5;

const duration = computed(() => props.endSecond - props.startSecond);
const startPercent = computed(() => (props.startSecond / props.maxSeconds) * 100);

// The "window" showing the selected range as a percentage of track width
const windowPercent = computed(() => (duration.value / props.maxSeconds) * 100);

function formatTime(totalSeconds: number): string {
    const m = Math.floor(totalSeconds / 60);
    const s = totalSeconds % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
}

function getSecondsFromPosition(clientX: number): number {
    if (!trackRef.value) return 0;
    const rect = trackRef.value.getBoundingClientRect();
    const ratio = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
    return Math.round(ratio * props.maxSeconds);
}

function setStartAt(seconds: number) {
    const clamped = Math.max(0, Math.min(seconds, props.maxSeconds - MIN_CLIP));
    const end = Math.min(clamped + duration.value, props.maxSeconds);
    emit('update:startSecond', clamped);
    emit('update:endSecond', end);
}

function onPointerDown(e: PointerEvent) {
    dragging.value = true;
    (e.target as HTMLElement).setPointerCapture(e.pointerId);
    const seconds = getSecondsFromPosition(e.clientX);
    setStartAt(seconds - Math.floor(duration.value / 2));
}

function onPointerMove(e: PointerEvent) {
    if (!dragging.value) return;
    const seconds = getSecondsFromPosition(e.clientX);
    setStartAt(seconds - Math.floor(duration.value / 2));
}

function onPointerUp() {
    dragging.value = false;
}

function onTrackClick(e: MouseEvent) {
    const seconds = getSecondsFromPosition(e.clientX);
    setStartAt(seconds - Math.floor(duration.value / 2));
}

function adjustDuration(delta: number) {
    const newDuration = Math.max(MIN_CLIP, Math.min(props.maxDuration, duration.value + delta));
    const newEnd = Math.min(props.startSecond + newDuration, props.maxSeconds);
    emit('update:endSecond', newEnd);
}

</script>

<template>
    <div class="space-y-3">
        <!-- Time display -->
        <div class="flex items-center justify-between">
            <div class="text-center">
                <p class="font-mono text-lg font-bold text-emerald-400">{{ formatTime(startSecond) }}</p>
                <p class="text-[10px] text-muted-foreground">Inicio</p>
            </div>
            <div class="text-center">
                <p class="text-sm font-semibold text-foreground">{{ duration }}s</p>
                <p class="text-[10px] text-muted-foreground">Duración</p>
            </div>
            <div class="text-center">
                <p class="font-mono text-lg font-bold text-blue-400">{{ formatTime(endSecond) }}</p>
                <p class="text-[10px] text-muted-foreground">Fin</p>
            </div>
        </div>

        <!-- Slider track -->
        <div
            ref="trackRef"
            class="relative h-14 cursor-pointer touch-none select-none rounded-lg bg-border/30"
            @click="onTrackClick"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
        >
            <!-- Background track -->
            <div class="absolute top-1/2 h-2 w-full -translate-y-1/2 rounded-full bg-border"></div>

            <!-- Selected window -->
            <div
                class="absolute top-1/2 -translate-y-1/2 rounded-md border-2 border-emerald-500 bg-emerald-500/20"
                :class="dragging ? 'shadow-lg shadow-emerald-500/20' : ''"
                :style="{
                    left: `${startPercent}%`,
                    width: `max(${windowPercent}%, 12px)`,
                    height: '36px',
                }"
                @pointerdown="onPointerDown"
            >
                <!-- Grab indicator -->
                <div class="flex h-full items-center justify-center gap-0.5">
                    <div class="h-3 w-0.5 rounded-full bg-emerald-400/60"></div>
                    <div class="h-3 w-0.5 rounded-full bg-emerald-400/60"></div>
                    <div class="h-3 w-0.5 rounded-full bg-emerald-400/60"></div>
                </div>
            </div>
        </div>

        <!-- Track time markers -->
        <div class="flex justify-between text-[10px] text-muted-foreground/50">
            <span>0:00</span>
            <span>{{ formatTime(Math.floor(maxSeconds / 4)) }}</span>
            <span>{{ formatTime(Math.floor(maxSeconds / 2)) }}</span>
            <span>{{ formatTime(Math.floor(maxSeconds * 3 / 4)) }}</span>
            <span>{{ formatTime(maxSeconds) }}</span>
        </div>

        <!-- Duration adjuster -->
        <div class="flex items-center justify-center gap-2">
            <button
                type="button"
                class="flex size-9 items-center justify-center rounded-lg border border-border text-muted-foreground transition-colors hover:bg-accent active:scale-95 disabled:opacity-30"
                :disabled="duration <= MIN_CLIP"
                @click="adjustDuration(-5)"
            >
                <Minus class="size-4" />
            </button>
            <span class="w-16 text-center text-sm font-semibold tabular-nums">{{ duration }}s</span>
            <button
                type="button"
                class="flex size-9 items-center justify-center rounded-lg border border-border text-muted-foreground transition-colors hover:bg-accent active:scale-95 disabled:opacity-30"
                :disabled="duration >= maxDuration"
                @click="adjustDuration(5)"
            >
                <Plus class="size-4" />
            </button>
        </div>
    </div>
</template>
