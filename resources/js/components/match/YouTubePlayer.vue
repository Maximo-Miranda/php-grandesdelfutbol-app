<script setup lang="ts">
import { ChevronLeft, ChevronRight, Gauge, Maximize, Minimize } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    videoId: string;
}>();

const containerId = `yt-player-${props.videoId}`;
const wrapperRef = ref<HTMLElement | null>(null);
const currentTime = ref('0:00');
const duration = ref('0:00');
const playbackRate = ref(1);
const isReady = ref(false);
const isFullscreen = ref(false);

let player: any = null;
let timeInterval: ReturnType<typeof setInterval> | null = null;

const SPEEDS = [0.5, 0.75, 1, 1.25, 1.5, 2];

function formatTime(seconds: number): string {
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m}:${s.toString().padStart(2, '0')}`;
}

function loadYouTubeApi(): Promise<void> {
    if (window.YT?.Player) return Promise.resolve();

    return new Promise((resolve) => {
        if (document.querySelector('script[src*="youtube.com/iframe_api"]')) {
            const check = setInterval(() => {
                if (window.YT?.Player) {
                    clearInterval(check);
                    resolve();
                }
            }, 100);
            return;
        }

        (window as any).onYouTubeIframeAPIReady = resolve;
        const tag = document.createElement('script');
        tag.src = 'https://www.youtube.com/iframe_api';
        document.head.appendChild(tag);
    });
}

function initPlayer() {
    player = new window.YT.Player(containerId, {
        videoId: props.videoId,
        playerVars: {
            autoplay: 0,
            controls: 1,
            modestbranding: 1,
            rel: 0,
            playsinline: 1,
            fs: 0,
        },
        events: {
            onReady: () => {
                isReady.value = true;
                duration.value = formatTime(player.getDuration());
                startTimeTracking();
            },
        },
    });
}

function startTimeTracking() {
    stopTimeTracking();
    timeInterval = setInterval(() => {
        if (player?.getCurrentTime) {
            currentTime.value = formatTime(player.getCurrentTime());
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
    if (!player?.getCurrentTime) return;
    const time = player.getCurrentTime() + seconds;
    player.seekTo(Math.max(0, time), true);
}

function cycleSpeed() {
    const currentIndex = SPEEDS.indexOf(playbackRate.value);
    const nextIndex = (currentIndex + 1) % SPEEDS.length;
    playbackRate.value = SPEEDS[nextIndex];
    player?.setPlaybackRate(playbackRate.value);
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

watch(() => props.videoId, async (newId) => {
    if (player?.loadVideoById) {
        player.loadVideoById(newId);
    }
});

onMounted(async () => {
    document.addEventListener('fullscreenchange', onFullscreenChange);
    await loadYouTubeApi();
    initPlayer();
});

onBeforeUnmount(() => {
    document.removeEventListener('fullscreenchange', onFullscreenChange);
    stopTimeTracking();
    player?.destroy();
    player = null;
});
</script>

<template>
    <div ref="wrapperRef" class="flex flex-col" :class="isFullscreen ? 'h-screen justify-center bg-black' : ''">
        <div class="aspect-video w-full overflow-hidden" :class="isFullscreen ? '' : 'rounded-xl border border-border'">
            <div :id="containerId" class="h-full w-full" />
        </div>

        <!-- Controls — visible in both normal and fullscreen -->
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
                <Button type="button" variant="ghost" size="sm" class="h-7 gap-1 px-2 text-xs" :class="isFullscreen ? 'text-white hover:text-white hover:bg-white/20' : ''" @click="seekRelative(-10)">
                    <ChevronLeft class="size-3.5" />
                    10s
                </Button>
                <Button type="button" variant="ghost" size="sm" class="h-7 gap-1 px-2 text-xs" :class="isFullscreen ? 'text-white hover:text-white hover:bg-white/20' : ''" @click="seekRelative(10)">
                    10s
                    <ChevronRight class="size-3.5" />
                </Button>
                <Button type="button" variant="ghost" size="sm" class="h-7 gap-1 px-2 text-xs" :class="isFullscreen ? 'text-white hover:text-white hover:bg-white/20' : ''" @click="cycleSpeed">
                    <Gauge class="size-3.5" />
                    {{ playbackRate }}x
                </Button>
                <Button type="button" variant="ghost" size="sm" class="h-7 px-2" :class="isFullscreen ? 'text-white hover:text-white hover:bg-white/20' : ''" @click="toggleFullscreen">
                    <Minimize v-if="isFullscreen" class="size-3.5" />
                    <Maximize v-else class="size-3.5" />
                </Button>
            </div>
        </div>
    </div>
</template>
