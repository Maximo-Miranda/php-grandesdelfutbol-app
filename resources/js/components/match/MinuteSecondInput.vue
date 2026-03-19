<script setup lang="ts">
import { ChevronDown, ChevronUp, Clock, Pencil } from 'lucide-vue-next';
import { onUnmounted, ref, watch } from 'vue';
import { formatEventTime } from '@/lib/utils';

const minute = defineModel<number>('minute', { required: true });
const second = defineModel<number>('second', { required: true });

const props = defineProps<{
    manualMode: boolean;
}>();

const emit = defineEmits<{
    toggleManual: [];
}>();

function adjustTime(delta: number) {
    let totalSeconds = minute.value * 60 + second.value + delta;
    if (totalSeconds < 0) totalSeconds = 0;
    if (totalSeconds > 200 * 60 + 59) totalSeconds = 200 * 60 + 59;
    minute.value = Math.floor(totalSeconds / 60);
    second.value = totalSeconds % 60;
}

// --- Long-press repeat ---
let repeatTimer: ReturnType<typeof setTimeout> | null = null;
let repeatInterval: ReturnType<typeof setTimeout> | null = null;

function startRepeat(delta: number) {
    stopRepeat();
    adjustTime(delta);
    repeatTimer = setTimeout(() => {
        let speed = 150;
        const tick = () => {
            adjustTime(delta);
            speed = Math.max(50, speed - 10);
            repeatInterval = setTimeout(tick, speed);
        };
        repeatInterval = setTimeout(tick, speed);
    }, 400);
}

function stopRepeat() {
    if (repeatTimer) { clearTimeout(repeatTimer); repeatTimer = null; }
    if (repeatInterval) { clearTimeout(repeatInterval); repeatInterval = null; }
}

onUnmounted(stopRepeat);

// --- Hint visibility ---
const showHint = ref(false);
let hintTimeout: ReturnType<typeof setTimeout> | null = null;

watch(() => props.manualMode, (isManual) => {
    if (hintTimeout) { clearTimeout(hintTimeout); hintTimeout = null; }
    if (isManual) {
        showHint.value = true;
        hintTimeout = setTimeout(() => { showHint.value = false; }, 5000);
    } else {
        showHint.value = false;
    }
});
</script>

<template>
    <div>
        <!-- Collapsed: show time + pencil to edit -->
        <button
            v-if="!manualMode"
            class="flex min-h-[44px] items-center gap-2 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-base font-bold tabular-nums text-emerald-400 transition-all active:scale-95 hover:bg-emerald-500/15 font-mono"
            @click="emit('toggleManual')"
        >
            <Clock class="size-4" />
            <span>{{ formatEventTime(minute, second) }}</span>
            <Pencil class="size-3 opacity-50" />
        </button>

        <!-- Expanded: editable time control -->
        <div v-else>
            <div class="flex items-center gap-0.5 rounded-xl border-2 border-amber-400/40 bg-amber-500/5 p-1.5">
                <!-- Minus -->
                <button
                    class="flex size-10 items-center justify-center rounded-lg bg-amber-500/10 text-amber-400 transition-colors hover:bg-amber-500/20 active:scale-95 select-none touch-none"
                    @pointerdown.prevent="startRepeat(-1)"
                    @pointerup="stopRepeat"
                    @pointerleave="stopRepeat"
                    @pointercancel="stopRepeat"
                    @contextmenu.prevent
                >
                    <ChevronDown class="size-5" />
                </button>

                <!-- Min input -->
                <div class="flex flex-col items-center">
                    <input
                        v-model.number="minute"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        class="w-10 bg-transparent px-0.5 py-1 text-center text-lg font-bold tabular-nums text-amber-400 outline-none"
                    />
                    <span class="text-[7px] font-bold tracking-widest text-amber-400/50 uppercase">min</span>
                </div>

                <span class="text-lg font-bold text-amber-400/60">:</span>

                <!-- Seg input -->
                <div class="flex flex-col items-center">
                    <input
                        v-model.number="second"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        class="w-10 bg-transparent px-0.5 py-1 text-center text-lg font-bold tabular-nums text-amber-400 outline-none"
                    />
                    <span class="text-[7px] font-bold tracking-widest text-amber-400/50 uppercase">seg</span>
                </div>

                <!-- Plus -->
                <button
                    class="flex size-10 items-center justify-center rounded-lg bg-amber-500/10 text-amber-400 transition-colors hover:bg-amber-500/20 active:scale-95 select-none touch-none"
                    @pointerdown.prevent="startRepeat(1)"
                    @pointerup="stopRepeat"
                    @pointerleave="stopRepeat"
                    @pointercancel="stopRepeat"
                    @contextmenu.prevent
                >
                    <ChevronUp class="size-5" />
                </button>

                <!-- Auto button -->
                <button
                    class="ml-0.5 flex size-10 items-center justify-center rounded-lg border border-amber-400/30 text-amber-400/70 transition-colors hover:bg-amber-500/10 active:scale-95"
                    title="Volver a tiempo automatico"
                    @click="emit('toggleManual')"
                >
                    <Clock class="size-4" />
                </button>
            </div>

            <!-- Hint -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-all duration-500 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <p v-if="showHint" class="mt-1 text-right text-[9px] text-muted-foreground">
                    Mantener presionado para avance rapido
                </p>
            </Transition>
        </div>
    </div>
</template>
