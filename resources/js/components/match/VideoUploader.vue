<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import AwsS3 from '@uppy/aws-s3';
import Uppy from '@uppy/core';
import { AlertTriangle, CheckCircle, CloudUpload, Loader2, Pause, Play, RefreshCw, Trash2, Upload, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import VideoPlayer from '@/components/match/VideoPlayer.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { getCsrfToken } from '@/lib/utils';

type VideoUploadData = {
    ulid: string;
    status: 'uploading' | 'encoding' | 'ready' | 'failed';
    original_filename: string | null;
    duration_seconds: number | null;
    video_offset_seconds: number;
    error_message: string | null;
    youtube_video_id?: string | null;
    youtube_embed_url?: string | null;
    youtube_url?: string | null;
};

type Props = {
    clubUlid: string;
    matchUlid: string;
    existingUpload?: VideoUploadData | null;
    embedUrl?: string | null;
    s3VideoUrl?: string | null;
};

const props = defineProps<Props>();
const emit = defineEmits<{ uploaded: [] }>();

const status = ref<'idle' | 'uploading' | 'paused' | 'encoding' | 'ready' | 'failed'>(
    props.existingUpload?.status === 'uploading' ? 'idle' : (props.existingUpload?.status ?? 'idle'),
);
const progress = ref(0);
const speed = ref('');
const errorMessage = ref(props.existingUpload?.error_message ?? '');
const showDeleteConfirm = ref(false);
const showUploadingWarning = ref(false);
let removeInertiaListener: (() => void) | null = null;
let wakeLock: WakeLockSentinel | null = null;

async function requestWakeLock() {
    try {
        if ('wakeLock' in navigator) {
            wakeLock = await navigator.wakeLock.request('screen');
        }
    } catch {
        // Wake Lock not supported or denied — non-critical
    }
}

async function releaseWakeLock() {
    if (wakeLock) {
        await wakeLock.release().catch(() => {});
        wakeLock = null;
    }
}

function startNavigationGuard() {
    window.addEventListener('beforeunload', onBeforeUnloadHandler);
    requestWakeLock();
    removeInertiaListener = router.on('before', (event) => {
        if (status.value === 'uploading' || status.value === 'paused') {
            event.preventDefault();
            showUploadingWarning.value = true;
            return false;
        }
    });
}

function stopNavigationGuard() {
    window.removeEventListener('beforeunload', onBeforeUnloadHandler);
    releaseWakeLock();
    if (removeInertiaListener) {
        removeInertiaListener();
        removeInertiaListener = null;
    }
}
const uploadedFilename = ref(props.existingUpload?.original_filename ?? '');
const videoUploadUrl = computed(() => `/clubs/${props.clubUlid}/matches/${props.matchUlid}/video-upload`);

let uppy: Uppy | null = null;
let pollInterval: ReturnType<typeof setInterval> | null = null;
let lastLoaded = 0;
let lastTime = 0;
let lastS3Key: string | null = null;

const STATUS_LABELS: Record<string, string> = {
    idle: 'Sin video',
    uploading: 'Subiendo...',
    paused: 'Pausado',
    encoding: 'Procesando video...',
    ready: 'Video listo',
    failed: 'Error',
};

const STATUS_COLORS: Record<string, string> = {
    idle: 'text-muted-foreground',
    uploading: 'text-blue-400',
    paused: 'text-yellow-400',
    encoding: 'text-amber-400',
    ready: 'text-emerald-400',
    failed: 'text-red-400',
};

const statusLabel = computed(() => STATUS_LABELS[status.value] ?? '');
const statusColor = computed(() => STATUS_COLORS[status.value] ?? '');

function formatBytes(bytes: number): string {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    if (bytes < 1073741824) return (bytes / 1048576).toFixed(1) + ' MB';
    return (bytes / 1073741824).toFixed(2) + ' GB';
}


async function selectFile() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'video/*';

    input.onchange = async () => {
        const file = input.files?.[0];
        if (!file) return;

        if (file.size > 25 * 1073741824) {
            errorMessage.value = 'El archivo es demasiado grande. Maximo 25 GB.';
            return;
        }

        uploadedFilename.value = file.name;
        await startUpload(file);
    };

    input.click();
}

async function startUpload(file: File) {
    status.value = 'uploading';
    progress.value = 0;
    errorMessage.value = '';
    startNavigationGuard();

    try {
        // Clean up any stale upload before starting a new one
        if (props.existingUpload) {
            await fetch(videoUploadUrl.value, {
                method: 'DELETE',
                headers: freshHeaders(),
                credentials: 'same-origin',
            }).catch(() => {});
        }

        initUppy(file);
    } catch (err: any) {
        status.value = 'failed';
        errorMessage.value = err.message || 'Error al iniciar la subida.';
    }
}

function freshHeaders(): Record<string, string> {
    return {
        'X-XSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
    };
}

async function fetchOrThrow(input: RequestInfo, init?: RequestInit): Promise<Response> {
    const res = await fetch(input, init);

    if (!res.ok) {
        const text = await res.text().catch(() => '');
        throw new Error(`Error ${res.status}: ${text.slice(0, 200) || res.statusText}`);
    }

    return res;
}

function initUppy(file: File) {
    uppy = new Uppy({
        restrictions: {
            maxFileSize: 25 * 1073741824,
            allowedFileTypes: ['video/*'],
            maxNumberOfFiles: 1,
        },
        autoProceed: true,
    });

    // @ts-expect-error Uppy types require getUploadParameters but it's not needed for multipart-only uploads
    uppy.use(AwsS3, {
        shouldUseMultipart: (file) => (file.size ?? 0) > 100 * 1024 * 1024,

        getChunkSize(file) {
            const size = file.size ?? 0;
            const MB = 1024 * 1024;

            // Target ~100 parts to keep request count manageable
            const calculated = Math.ceil(size / 100);

            // Minimum 10MB, maximum 100MB per part
            return Math.min(Math.max(calculated, 10 * MB), 100 * MB);
        },

        async createMultipartUpload(file) {
            const res = await fetchOrThrow('/s3/multipart', {
                method: 'POST',
                headers: { ...freshHeaders(), 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    filename: file.name,
                    content_type: file.type || 'video/mp4',
                }),
            });
            const data = await res.json();
            lastS3Key = data.key;
            return data;
        },

        async signPart(file, { uploadId, key, partNumber }) {
            const res = await fetchOrThrow(
                `/s3/multipart/${uploadId}/${partNumber}?key=${encodeURIComponent(key)}`,
                { credentials: 'same-origin', headers: freshHeaders() },
            );
            return await res.json();
        },

        async listParts(file, { uploadId, key }) {
            const res = await fetchOrThrow(
                `/s3/multipart/${uploadId}?key=${encodeURIComponent(key)}`,
                { credentials: 'same-origin', headers: freshHeaders() },
            );
            return await res.json();
        },

        async completeMultipartUpload(file, { uploadId, key, parts }) {
            const res = await fetchOrThrow(`/s3/multipart/${uploadId}/complete`, {
                method: 'POST',
                headers: { ...freshHeaders(), 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ key, parts }),
            });
            return await res.json();
        },

        async abortMultipartUpload(file, { uploadId, key }) {
            await fetch(`/s3/multipart/${uploadId}`, {
                method: 'DELETE',
                headers: { ...freshHeaders(), 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ key }),
            });
        },
    });

    uppy.on('upload-progress', (_file, progressData) => {
        if (progressData.bytesTotal) {
            progress.value = Math.round((progressData.bytesUploaded / progressData.bytesTotal) * 100);
        }

        const now = Date.now();
        if (lastTime > 0 && now - lastTime > 500) {
            const bytesDiff = progressData.bytesUploaded - lastLoaded;
            const timeDiff = (now - lastTime) / 1000;
            const bytesPerSec = bytesDiff / timeDiff;
            speed.value = formatBytes(bytesPerSec) + '/s';
        }
        lastLoaded = progressData.bytesUploaded;
        lastTime = now;
    });

    uppy.on('upload-success', async () => {
        stopNavigationGuard();

        const s3Key = lastS3Key;

        try {
            const res = await fetch(videoUploadUrl.value, {
                method: 'POST',
                headers: { ...freshHeaders(), 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    filename: file.name,
                    filesize: file.size,
                    s3_key: s3Key,
                }),
            });

            if (!res.ok) {
                const data = await res.json();
                throw new Error(data.error || 'Error al registrar el video.');
            }

            status.value = 'encoding';
            startPolling();
        } catch (err: any) {
            status.value = 'failed';
            errorMessage.value = err.message || 'Error al registrar el video.';
        }
    });

    uppy.on('upload-error', (_file, error) => {
        status.value = 'failed';
        errorMessage.value = error?.message || 'Error durante la subida.';
    });

    uppy.addFile({
        name: file.name,
        type: file.type,
        data: file,
    });
}

function pauseUpload() {
    if (uppy) {
        uppy.pauseAll();
        status.value = 'paused';
    }
}

function unpauseUpload() {
    if (uppy) {
        uppy.resumeAll();
        status.value = 'uploading';
    }
}

async function cancelUpload() {
    if (uppy) {
        uppy.cancelAll();
        uppy = null;
    }

    status.value = 'idle';
    progress.value = 0;
    speed.value = '';

    try {
        await fetch(videoUploadUrl.value, {
            method: 'DELETE',
            headers: freshHeaders(),
            credentials: 'same-origin',
        });
    } catch {
        // Ignore cleanup errors
    }
}

async function deleteVideo() {
    try {
        await fetch(videoUploadUrl.value, {
            method: 'DELETE',
            headers: freshHeaders(),
            credentials: 'same-origin',
        });
        showDeleteConfirm.value = false;
        status.value = 'idle';
        uploadedFilename.value = '';
    } catch {
        errorMessage.value = 'Error al eliminar el video.';
    }
}

const refreshing = ref(false);

async function fetchAndSyncStatus(): Promise<void> {
    const response = await fetch(videoUploadUrl.value, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
    });
    const data = await response.json();

    if (data.video_upload?.status === 'ready') {
        status.value = 'ready';
        stopPolling();
        emit('uploaded');
    } else if (data.video_upload?.status === 'failed') {
        status.value = 'failed';
        errorMessage.value = data.video_upload.error_message || 'El procesamiento falló.';
        stopPolling();
    }
}

async function refreshStatus() {
    refreshing.value = true;
    try {
        await fetchAndSyncStatus();
    } catch {
        // Ignore
    } finally {
        refreshing.value = false;
    }
}

function startPolling() {
    stopPolling();
    pollInterval = setInterval(async () => {
        try {
            await fetchAndSyncStatus();
        } catch {
            // Retry on next interval
        }
    }, 5000);
}

function stopPolling() {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
}

function onBeforeUnloadHandler(e: BeforeUnloadEvent) {
    e.preventDefault();
}

function onVisibilityChange() {
    if (document.visibilityState === 'visible' && (status.value === 'uploading' || status.value === 'paused')) {
        requestWakeLock();
    }
}

onMounted(() => {
    document.addEventListener('visibilitychange', onVisibilityChange);

    if (status.value === 'encoding') {
        startPolling();
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', onVisibilityChange);
    stopPolling();
    stopNavigationGuard();
    if (uppy) {
        uppy.cancelAll();
        uppy = null;
    }
});
</script>

<template>
    <div class="space-y-3">
        <!-- Idle: No video -->
        <div v-if="status === 'idle'" class="rounded-lg border-2 border-dashed border-border p-6 text-center">
            <CloudUpload class="mx-auto mb-2 size-10 text-muted-foreground" />
            <p class="mb-3 text-sm text-muted-foreground">Sube el video del partido directamente.</p>
            <Button type="button" variant="outline" class="gap-2" @click="selectFile">
                <Upload class="size-4" />
                Seleccionar video
            </Button>
            <p class="mt-2 text-xs text-muted-foreground">Maximo 25 GB. Formatos: MP4, MOV, AVI, MKV</p>
        </div>

        <!-- Uploading / Paused -->
        <div v-else-if="status === 'uploading' || status === 'paused'" class="rounded-lg border border-border p-4">
            <div class="mb-2 flex items-center justify-between">
                <span class="text-sm font-medium" :class="statusColor">{{ statusLabel }}</span>
                <span class="text-xs text-muted-foreground">{{ uploadedFilename }}</span>
            </div>

            <!-- Progress bar -->
            <div class="mb-2 h-2.5 w-full overflow-hidden rounded-full bg-secondary">
                <div
                    class="h-full rounded-full transition-all duration-300"
                    :class="status === 'paused' ? 'bg-yellow-500' : 'bg-blue-500'"
                    :style="{ width: `${progress}%` }"
                />
            </div>

            <div class="flex items-center justify-between text-xs text-muted-foreground">
                <span>{{ progress }}%</span>
                <span v-if="speed && status === 'uploading'">{{ speed }}</span>
            </div>

            <!-- Actions -->
            <div class="mt-3 flex gap-2">
                <Button v-if="status === 'uploading'" type="button" variant="outline" size="sm" class="gap-1.5" @click="pauseUpload">
                    <Pause class="size-3.5" />
                    Pausar
                </Button>
                <Button v-if="status === 'paused'" type="button" variant="outline" size="sm" class="gap-1.5" @click="unpauseUpload">
                    <Play class="size-3.5" />
                    Reanudar
                </Button>
                <Button type="button" variant="ghost" size="sm" class="gap-1.5 text-destructive hover:text-destructive" @click="cancelUpload">
                    <X class="size-3.5" />
                    Cancelar
                </Button>
            </div>
        </div>

        <!-- Encoding -->
        <div v-else-if="status === 'encoding'" class="rounded-lg border border-amber-500/30 bg-amber-500/5 p-4">
            <div class="flex items-center gap-3">
                <Loader2 class="size-5 animate-spin text-amber-400" />
                <div class="flex-1">
                    <p class="text-sm font-medium text-amber-400">Procesando video...</p>
                    <p class="text-xs text-muted-foreground">Estamos procesando tu video. Esto puede tomar varios minutos.</p>
                </div>
                <Button type="button" variant="ghost" size="sm" class="shrink-0 gap-1.5" :disabled="refreshing" @click="refreshStatus">
                    <RefreshCw class="size-3.5" :class="refreshing ? 'animate-spin' : ''" />
                    Actualizar
                </Button>
            </div>
        </div>

        <!-- Ready -->
        <div v-else-if="status === 'ready'" class="space-y-3">
            <div class="flex items-center gap-2 text-sm text-emerald-400">
                <CheckCircle class="size-4" />
                <span class="font-medium">Video listo</span>
                <span class="text-xs text-muted-foreground">{{ uploadedFilename }}</span>
            </div>

            <!-- YouTube embed -->
            <div v-if="props.existingUpload?.youtube_embed_url" class="aspect-video w-full overflow-hidden rounded-lg border border-border">
                <iframe
                    :src="props.existingUpload.youtube_embed_url"
                    class="h-full w-full"
                    allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen
                />
            </div>
            <!-- S3 fallback player + YouTube retry -->
            <template v-else>
                <VideoPlayer v-if="props.s3VideoUrl" :src="props.s3VideoUrl" />
                <div class="mt-2 rounded-lg border border-border bg-muted/30 px-3 py-2 text-center">
                    <span class="text-xs text-muted-foreground">Video disponible desde la plataforma</span>
                </div>
            </template>

            <Button type="button" variant="ghost" size="sm" class="gap-1.5 text-destructive hover:text-destructive" @click="showDeleteConfirm = true">
                <Trash2 class="size-3.5" />
                Eliminar video
            </Button>
        </div>

        <!-- Failed -->
        <div v-else-if="status === 'failed'" class="rounded-lg border border-red-500/30 bg-red-500/5 p-4">
            <div class="flex items-center gap-3">
                <AlertTriangle class="size-5 text-red-400" />
                <div class="flex-1">
                    <p class="text-sm font-medium text-red-400">Error</p>
                    <p class="text-xs text-muted-foreground">{{ errorMessage }}</p>
                </div>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <Button type="button" variant="outline" size="sm" class="gap-1.5" :disabled="refreshing" @click="refreshStatus">
                    <RefreshCw class="size-3.5" :class="refreshing ? 'animate-spin' : ''" />
                    Verificar estado
                </Button>
                <Button type="button" variant="outline" size="sm" class="gap-1.5" @click="cancelUpload(); selectFile();">
                    <Upload class="size-3.5" />
                    Subir otro video
                </Button>
            </div>
        </div>

        <!-- Upload in progress warning dialog -->
        <Dialog v-model:open="showUploadingWarning">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Video subiendo</DialogTitle>
                    <DialogDescription>
                        Se esta subiendo un video. Si sales de esta pagina la subida se cancelara y tendras que empezar de nuevo.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose as-child>
                        <Button class="w-full">Entendido, me quedo</Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete confirmation dialog -->
        <Dialog v-model:open="showDeleteConfirm">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Eliminar video del partido</DialogTitle>
                    <DialogDescription>
                        Se eliminara el video de este partido. Los reels generados se mantendran pero no se podran generar nuevos hasta subir otro video.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-0">
                    <DialogClose as-child>
                        <Button variant="outline">Cancelar</Button>
                    </DialogClose>
                    <Button variant="destructive" class="gap-2" @click="deleteVideo">
                        <Trash2 class="size-4" />
                        Eliminar video
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
