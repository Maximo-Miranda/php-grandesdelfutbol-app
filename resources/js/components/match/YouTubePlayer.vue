<script setup lang="ts">
import { ChevronLeft, ChevronRight, Gauge, Maximize, Minimize } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { useVideoSync } from '@/composables/useVideoSync';

const props = defineProps<{
    videoId: string;
    matchUlid?: string;
}>();

const videoSync = props.matchUlid ? useVideoSync(props.matchUlid, 'producer') : null;
let remotePause = false;

videoSync?.onPauseRequested(() => {
    remotePause = true;
    player?.pauseVideo();
});

const containerId = `yt-player-${props.videoId}`;
const wrapperRef = ref<HTMLElement | null>(null);
const currentTime = ref('0:00');
const duration = ref('0:00');
const playbackRate = ref(1);
const isReady = ref(false);
const isFullscreen = ref(false);
const isPlaying = ref(false);
const fsButtonClass = computed(() => isFullscreen.value ? 'text-white hover:text-white hover:bg-white/20' : '');

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

    return new Promise((resolve, reject) => {
        const timeout = setTimeout(() => reject(new Error('YouTube API load timeout')), 10000);

        if (document.querySelector('script[src*="youtube.com/iframe_api"]')) {
            const check = setInterval(() => {
                if (window.YT?.Player) {
                    clearInterval(check);
                    clearTimeout(timeout);
                    resolve();
                }
            }, 100);
            return;
        }

        (window as any).onYouTubeIframeAPIReady = () => {
            clearTimeout(timeout);
            resolve();
        };
        const tag = document.createElement('script');
        tag.src = 'https://www.youtube.com/iframe_api';
        tag.onerror = () => {
            clearTimeout(timeout);
            reject(new Error('Failed to load YouTube API'));
        };
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
            onStateChange: (event: { data: number }) => {
                isPlaying.value = event.data === 1;

                if (remotePause) {
                    remotePause = false;
                    return;
                }

                if (player?.getCurrentTime && videoSync) {
                    if (isPlaying.value) {
                        videoSync.claimControl(player.getCurrentTime());
                    } else {
                        videoSync.sendUpdate(player.getCurrentTime(), false);
                    }
                }
            },
        },
    });
}

function startTimeTracking() {
    stopTimeTracking();
    timeInterval = setInterval(() => {
        if (player?.getCurrentTime) {
            const seconds = player.getCurrentTime();
            currentTime.value = formatTime(seconds);
            if (isPlaying.value) {
                videoSync?.sendUpdate(seconds, true);
            }
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
    if (!player?.getCurrentTime || !player?.getDuration) return;
    const target = player.getCurrentTime() + seconds;
    player.seekTo(Math.max(0, Math.min(target, player.getDuration())), true);
}

function cycleSpeed() {
    const currentIndex = SPEEDS.indexOf(playbackRate.value);
    const nextIndex = (currentIndex + 1) % SPEEDS.length;
    playbackRate.value = SPEEDS[nextIndex];
    player?.setPlaybackRate(playbackRate.value);
}

async function toggleFullscreen() {
    if (!wrapperRef.value) return;

    if (!isFullscreen.value) {
        try {
            await wrapperRef.value.requestFullscreen?.();
            await (screen.orientation as any)?.lock?.('landscape').catch(() => {});
        } catch { /* iOS doesn't support Fullscreen API */ }
        isFullscreen.value = true;
    } else {
        try {
            screen.orientation?.unlock?.();
            if (document.fullscreenElement) {
                await document.exitFullscreen?.();
            }
        } catch { /* ignore */ }
        isFullscreen.value = false;
    }
}

function onFullscreenChange() {
    const nativeFullscreen = !!document.fullscreenElement;
    if (!nativeFullscreen && isFullscreen.value) {
        isFullscreen.value = false;
        screen.orientation?.unlock?.();
    }
}

function onKeydown(e: KeyboardEvent) {
    if (!isReady.value || !isFullscreen.value) return;

    switch (e.key) {
        case 'ArrowLeft':
            seekRelative(-10);
            e.preventDefault();
            break;
        case 'ArrowRight':
            seekRelative(10);
            e.preventDefault();
            break;
        case 'Escape':
            if (isFullscreen.value) toggleFullscreen();
            break;
    }
}

watch(() => props.videoId, (newId) => {
    if (player?.loadVideoById) {
        player.loadVideoById(newId);
    }
});

onMounted(async () => {
    document.addEventListener('fullscreenchange', onFullscreenChange);
    document.addEventListener('keydown', onKeydown);

    try {
        await loadYouTubeApi();
        initPlayer();
    } catch {
        // YouTube API failed to load — player won't initialize, iframe stays empty
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('fullscreenchange', onFullscreenChange);
    document.removeEventListener('keydown', onKeydown);
    stopTimeTracking();
    player?.destroy();
    player = null;
});
</script>

<template>
    <div ref="wrapperRef" class="flex flex-col" :class="isFullscreen ? 'fixed inset-0 z-[60] flex flex-col bg-black' : ''">
        <div :class="isFullscreen ? 'flex-1 min-h-0' : 'aspect-video w-full overflow-hidden rounded-xl border border-border'">
            <div :id="containerId" class="h-full w-full" />
        </div>

        <div
            v-if="isReady"
            class="flex shrink-0 items-center justify-between px-3 py-1.5"
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
