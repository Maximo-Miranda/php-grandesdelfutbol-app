import { computed, onBeforeUnmount, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';

interface VideoSyncMessage {
    type: 'time' | 'pause-others';
    seconds: number;
    playing: boolean;
    timestamp: number;
    producerId: string;
}

interface SharedState {
    seconds: Ref<number>;
    playing: Ref<boolean>;
    activeProducerId: string | null;
    refCount: number;
}

interface UseVideoSyncReturn {
    currentSeconds: Ref<number>;
    isPlaying: Ref<boolean>;
    syncedMinute: ComputedRef<number>;
    syncedSecond: ComputedRef<number>;
    sendUpdate: (seconds: number, playing: boolean) => void;
    claimControl: (seconds: number) => void;
    onPauseRequested: (callback: () => void) => void;
    destroy: () => void;
}

const STALE_THRESHOLD = 3000;
const sharedState = new Map<string, SharedState>();
const hasBroadcastChannel = typeof BroadcastChannel !== 'undefined';

function generateId(): string {
    return `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
}

function getOrCreateShared(matchUlid: string): SharedState {
    if (!sharedState.has(matchUlid)) {
        sharedState.set(matchUlid, {
            seconds: ref(0),
            playing: ref(false),
            activeProducerId: null,
            refCount: 0,
        });
    }

    const state = sharedState.get(matchUlid)!;
    state.refCount++;

    return state;
}

function releaseShared(matchUlid: string) {
    const state = sharedState.get(matchUlid);
    if (!state) return;

    state.refCount--;
    if (state.refCount <= 0) {
        sharedState.delete(matchUlid);
    }
}

export function useVideoSync(matchUlid: string, role: 'producer' | 'consumer'): UseVideoSyncReturn {
    const channelName = `video-sync-${matchUlid}`;
    const shared = getOrCreateShared(matchUlid);
    const producerId = role === 'producer' ? generateId() : '';

    let channel: BroadcastChannel | null = null;
    let storageListener: ((e: StorageEvent) => void) | null = null;
    let pauseCallback: (() => void) | null = null;

    function handleIncoming(msg: VideoSyncMessage) {
        if (Date.now() - msg.timestamp > STALE_THRESHOLD) return;

        if (msg.type === 'pause-others') {
            if (role === 'producer' && msg.producerId !== producerId) {
                pauseCallback?.();
            }
            return;
        }

        if (msg.playing) {
            shared.activeProducerId = msg.producerId;
        }

        if (msg.producerId !== shared.activeProducerId) return;

        shared.seconds.value = msg.seconds;
        shared.playing.value = msg.playing;
    }

    if (hasBroadcastChannel) {
        channel = new BroadcastChannel(channelName);
        channel.onmessage = (e: MessageEvent<VideoSyncMessage>) => handleIncoming(e.data);
    } else if (role === 'consumer') {
        storageListener = (e: StorageEvent) => {
            if (e.key !== channelName || !e.newValue) return;

            try {
                handleIncoming(JSON.parse(e.newValue));
            } catch {
                // Invalid JSON
            }
        };
        window.addEventListener('storage', storageListener);
    }

    function sendUpdate(seconds: number, playing: boolean) {
        shared.seconds.value = seconds;
        shared.playing.value = playing;

        if (playing) {
            shared.activeProducerId = producerId;
        }

        if (!playing) return;

        const msg: VideoSyncMessage = { type: 'time', seconds, playing, timestamp: Date.now(), producerId };

        if (channel) {
            channel.postMessage(msg);
        } else if (!hasBroadcastChannel) {
            localStorage.setItem(channelName, JSON.stringify(msg));
        }
    }

    function claimControl(seconds: number) {
        shared.activeProducerId = producerId;
        shared.seconds.value = seconds;
        shared.playing.value = true;

        if (channel) {
            channel.postMessage({ type: 'pause-others', seconds, playing: true, timestamp: Date.now(), producerId });
        }
    }

    function onPauseRequested(callback: () => void) {
        pauseCallback = callback;
    }

    function destroy() {
        channel?.close();
        channel = null;

        if (storageListener) {
            window.removeEventListener('storage', storageListener);
            storageListener = null;
        }

        pauseCallback = null;
        releaseShared(matchUlid);
    }

    onBeforeUnmount(destroy);

    return {
        currentSeconds: shared.seconds,
        isPlaying: shared.playing,
        syncedMinute: computed(() => Math.floor(shared.seconds.value / 60)),
        syncedSecond: computed(() => Math.floor(shared.seconds.value % 60)),
        sendUpdate,
        claimControl,
        onPauseRequested,
        destroy,
    };
}
