<script setup lang="ts">
import { ChevronLeft, ChevronRight, Gauge, Maximize, Minimize } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useVideoSync } from '@/composables/useVideoSync';

const props = defineProps<{
    streamUrl: string;
    matchUlid?: string;
}>();

const videoSync = props.matchUlid ? useVideoSync(props.matchUlid, 'producer') : null;
let remotePause = false;

videoSync?.onPauseRequested(() => {
    remotePause = true;
    videoRef.value?.pause();
});

const videoRef = ref<HTMLVideoElement | null>(null);
const wrapperRef = ref<HTMLElement | null>(null);
const currentTime = ref('0:00');
const duration = ref('0:00');
const playbackRate = ref(1);
const isReady = ref(false);
const isFullscreen = ref(false);
const isPlaying = ref(false);
const fsButtonClass = computed(() => isFullscreen.value ? 'text-white hover:text-white hover:bg-white/20' : '');

let timeInterval: ReturnType<typeof setInterval> | null = null;

const SPEEDS = [0.5, 0.75, 1, 1.25, 1.5, 2];

function formatTime(seconds: number): string {
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m}:${s.toString().padStart(2, '0')}`;
}

function onLoadedMetadata() {
    if (!videoRef.value) return;
    isReady.value = true;
    duration.value = formatTime(videoRef.value.duration);
    startTimeTracking();
}

function onPlay() {
    isPlaying.value = true;

    if (remotePause) {
        remotePause = false;
        return;
    }

    if (videoRef.value && videoSync) {
        videoSync.claimControl(videoRef.value.currentTime);
    }
}

function onPause() {
    isPlaying.value = false;

    if (remotePause) {
        remotePause = false;
        return;
    }

    if (videoRef.value && videoSync) {
        videoSync.sendUpdate(videoRef.value.currentTime, false);
    }
}

function startTimeTracking() {
    stopTimeTracking();
    timeInterval = setInterval(() => {
        if (!videoRef.value) return;
        const seconds = videoRef.value.currentTime;
        currentTime.value = formatTime(seconds);
        if (isPlaying.value) {
            videoSync?.sendUpdate(seconds, true);
        }
    }, 500);
}

function stopTimeTracking() {
    if (timeInterval) {
        clearInterval(timeInterval);
        timeInterval = null;
    }
}

function seekRelative(seconds: number) {
    if (!videoRef.value) return;
    const target = videoRef.value.currentTime + seconds;
    videoRef.value.currentTime = Math.max(0, Math.min(target, videoRef.value.duration || 0));
}

function cycleSpeed() {
    const currentIndex = SPEEDS.indexOf(playbackRate.value);
    const nextIndex = (currentIndex + 1) % SPEEDS.length;
    playbackRate.value = SPEEDS[nextIndex];
    if (videoRef.value) {
        videoRef.value.playbackRate = playbackRate.value;
    }
}

async function toggleFullscreen() {
    if (!wrapperRef.value) return;

    if (document.fullscreenElement) {
        await document.exitFullscreen();
    } else {
        await wrapperRef.value.requestFullscreen();
    }
}

function onFullscreenChange() {
    isFullscreen.value = !!document.fullscreenElement;
}

onMounted(() => {
    document.addEventListener('fullscreenchange', onFullscreenChange);
});

onBeforeUnmount(() => {
    document.removeEventListener('fullscreenchange', onFullscreenChange);
    stopTimeTracking();
});
</script>

<template>
    <div ref="wrapperRef" class="flex flex-col" :class="isFullscreen ? 'h-screen justify-center bg-black' : ''">
        <div class="aspect-video w-full overflow-hidden" :class="isFullscreen ? '' : 'rounded-xl border border-border'">
            <video
                ref="videoRef"
                :src="streamUrl"
                class="h-full w-full"
                controls
                preload="metadata"
                playsinline
                @loadedmetadata="onLoadedMetadata"
                @play="onPlay"
                @pause="onPause"
            />
        </div>

        <div
            v-if="isReady"
            class="flex items-center justify-between px-3 py-1.5"
            :class="isFullscreen
                ? 'bg-black/80 text-white'
                : 'mt-2 rounded-lg border border-border bg-muted/30'
            "
        >
            <span class="text-xs" :class="isFullscreen ? 'text-white/70' : 'text-muted-foreground'">
                {{ currentTime }} / {{ duration }}
            </span>

            <div class="flex items-center gap-1">
                <Button type="button" variant="ghost" size="sm" class="h-7 gap-1 px-2 text-xs" :class="fsButtonClass" @click="seekRelative(-10)">
                    <ChevronLeft class="size-3.5" />
                    10s
                </Button>
                <Button type="button" variant="ghost" size="sm" class="h-7 gap-1 px-2 text-xs" :class="fsButtonClass" @click="seekRelative(10)">
                    10s
                    <ChevronRight class="size-3.5" />
                </Button>
                <Button type="button" variant="ghost" size="sm" class="h-7 gap-1 px-2 text-xs" :class="fsButtonClass" @click="cycleSpeed">
                    <Gauge class="size-3.5" />
                    {{ playbackRate }}x
                </Button>
                <Button type="button" variant="ghost" size="sm" class="h-7 px-2" :class="fsButtonClass" @click="toggleFullscreen">
                    <Minimize v-if="isFullscreen" class="size-3.5" />
                    <Maximize v-else class="size-3.5" />
                </Button>
            </div>
        </div>
    </div>
</template>
