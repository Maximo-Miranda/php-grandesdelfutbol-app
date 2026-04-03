<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle, CloudUpload, Loader2, Pause, Play, RefreshCw, RotateCcw, Trash2, Upload, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import DrivePlayer from '@/components/match/DrivePlayer.vue';
import VideoPlayer from '@/components/match/VideoPlayer.vue';
import YouTubePlayer from '@/components/match/YouTubePlayer.vue';
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
import {
    deletePendingUpload,
    executeUpload,
    getPendingUpload,
    savePendingUpload
} from '@/lib/drive-uploader';
import type { PendingUpload } from '@/lib/drive-uploader';
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
    drive_embed_url?: string | null;
    embed_url?: string | null;
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

const status = ref<'idle' | 'uploading' | 'paused' | 'encoding' | 'ready' | 'failed' | 'resumable'>(
    props.existingUpload?.status === 'uploading' ? 'idle' : (props.existingUpload?.status ?? 'idle'),
);
const progress = ref(0);
const speed = ref('');
const errorMessage = ref(props.existingUpload?.error_message ?? '');
const videoData = ref<VideoUploadData | null>(props.existingUpload ?? null);
const showDeleteConfirm = ref(false);
const showUploadingWarning = ref(false);
const pendingVisitUrl = ref<string | null>(null);
let removeInertiaListener: (() => void) | null = null;
let bypassNavigationGuard = false;
let wakeLock: WakeLockSentinel | null = null;

// Drive-specific state
const pendingDriveUpload = ref<PendingUpload | null>(null);
let driveAbortController: AbortController | null = null;

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
        if (bypassNavigationGuard) {
            bypassNavigationGuard = false;
            return;
        }
        if (status.value === 'uploading' || status.value === 'paused') {
            event.preventDefault();
            pendingVisitUrl.value = event.detail.visit.url.href;
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
const driveUploadBaseUrl = computed(() => `/clubs/${props.clubUlid}/matches/${props.matchUlid}/drive-upload`);

let pollInterval: ReturnType<typeof setInterval> | null = null;
let lastLoaded = 0;
let lastTime = 0;

const STATUS_LABELS: Record<string, string> = {
    idle: 'Sin video',
    uploading: 'Subiendo...',
    paused: 'Pausado',
    encoding: 'Procesando video...',
    ready: 'Video listo',
    failed: 'Error',
    resumable: 'Subida pendiente',
};

const STATUS_COLORS: Record<string, string> = {
    idle: 'text-muted-foreground',
    uploading: 'text-blue-400',
    paused: 'text-yellow-400',
    encoding: 'text-amber-400',
    ready: 'text-emerald-400',
    failed: 'text-red-400',
    resumable: 'text-blue-400',
};

const statusLabel = computed(() => STATUS_LABELS[status.value] ?? '');
const statusColor = computed(() => STATUS_COLORS[status.value] ?? '');

function formatBytes(bytes: number): string {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    if (bytes < 1073741824) return (bytes / 1048576).toFixed(1) + ' MB';
    return (bytes / 1073741824).toFixed(2) + ' GB';
}

function pickVideoFile(onFile: (file: File) => void) {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'video/*';
    input.onchange = () => {
        const file = input.files?.[0];
        if (file) onFile(file);
    };
    input.click();
}

async function selectFile() {
    pickVideoFile(async (file) => {
        if (file.size > 25 * 1073741824) {
            errorMessage.value = 'El archivo es demasiado grande. Maximo 25 GB.';
            return;
        }

        uploadedFilename.value = file.name;
        await startUpload(file);
    });
}

/**
 * Select a file to resume a pending Drive upload.
 * Validates that the file matches the pending upload (name + size).
 */
function selectFileForResume() {
    pickVideoFile(async (file) => {
        if (!pendingDriveUpload.value) return;

        if (file.name !== pendingDriveUpload.value.fileName || file.size !== pendingDriveUpload.value.fileSize) {
            errorMessage.value = `Selecciona el mismo archivo: "${pendingDriveUpload.value.fileName}" (${formatBytes(pendingDriveUpload.value.fileSize)})`;
            return;
        }

        errorMessage.value = '';
        await resumeDriveUpload(file);
    });
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

        await startDriveUpload(file);
    } catch (err: any) {
        status.value = 'failed';
        errorMessage.value = err.message || 'Error al iniciar la subida.';
        stopNavigationGuard();
    }
}

function freshHeaders(): Record<string, string> {
    return {
        'X-XSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
    };
}

function jsonHeaders(): Record<string, string> {
    return { ...freshHeaders(), 'Content-Type': 'application/json' };
}

function updateSpeed(bytesUploaded: number) {
    const now = Date.now();
    if (lastTime > 0 && now - lastTime > 500) {
        const bytesDiff = bytesUploaded - lastLoaded;
        const timeDiff = (now - lastTime) / 1000;
        if (timeDiff > 0) {
            speed.value = formatBytes(bytesDiff / timeDiff) + '/s';
        }
    }
    lastLoaded = bytesUploaded;
    lastTime = now;
}

async function fetchOrThrow(input: RequestInfo, init?: RequestInit): Promise<Response> {
    const res = await fetch(input, init);

    if (!res.ok) {
        const text = await res.text().catch(() => '');
        throw new Error(`Error ${res.status}: ${text.slice(0, 200) || res.statusText}`);
    }

    return res;
}

// ── Google Drive Upload ────────────────────────────────────────

async function startDriveUpload(file: File) {
    const res = await fetchOrThrow(`${driveUploadBaseUrl.value}/init`, {
        method: 'POST',
        headers: jsonHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify({
            filename: file.name,
            filesize: file.size,
            content_type: file.type || 'video/mp4',
        }),
    });

    const data = await res.json();

    const pending: PendingUpload = {
        matchUlid: props.matchUlid,
        sessionUri: data.session_uri,
        accessToken: data.access_token,
        expiresAt: data.expires_at,
        fileName: file.name,
        fileSize: file.size,
        fileType: file.type || 'video/mp4',
        bytesUploaded: 0,
        uploadUlid: data.upload_ulid,
        createdAt: Date.now(),
    };

    await savePendingUpload(pending);
    pendingDriveUpload.value = pending;
    driveAbortController = new AbortController();

    await executeDriveUpload(file, pending);
}

async function resumeDriveUpload(file: File) {
    if (!pendingDriveUpload.value) return;

    status.value = 'uploading';
    errorMessage.value = '';
    startNavigationGuard();

    try {
        // Probe via backend to avoid CORS issues
        const probeRes = await fetchOrThrow(`${driveUploadBaseUrl.value}/probe`, {
            method: 'POST',
            headers: jsonHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({
                session_uri: pendingDriveUpload.value.sessionUri,
                total_size: pendingDriveUpload.value.fileSize,
            }),
        });

        const probeResult = await probeRes.json();

        if (probeResult.complete) {
            await onDriveUploadComplete(probeResult.drive_file_id!);
            return;
        }

        // Update token and progress from backend response
        pendingDriveUpload.value.bytesUploaded = probeResult.bytes_uploaded;
        pendingDriveUpload.value.accessToken = probeResult.access_token;
        pendingDriveUpload.value.expiresAt = probeResult.expires_at;
        await savePendingUpload(pendingDriveUpload.value);
        driveAbortController = new AbortController();

        await executeDriveUpload(file, pendingDriveUpload.value);
    } catch (err: any) {
        stopNavigationGuard();

        // If session expired, clean up and reset to idle so user can start fresh
        if (err.message?.includes('expirado') || err.message?.includes('expired')) {
            await deletePendingUpload(props.matchUlid);
            pendingDriveUpload.value = null;
            status.value = 'idle';
            errorMessage.value = '';
        } else {
            status.value = 'failed';
            errorMessage.value = err.message || 'Error al reanudar la subida.';
        }
    }
}

async function executeDriveUpload(file: File, pending: PendingUpload) {
    lastLoaded = pending.bytesUploaded;
    lastTime = Date.now();

    await executeUpload(file, pending, {
        onProgress(bytesUploaded, totalBytes) {
            progress.value = Math.round((bytesUploaded / totalBytes) * 100);
            updateSpeed(bytesUploaded);
        },

        async onComplete(driveFileId) {
            await onDriveUploadComplete(driveFileId);
        },

        onError(error) {
            status.value = 'failed';
            errorMessage.value = error.message;
            stopNavigationGuard();
        },

        async onTokenRefresh() {
            const res = await fetchOrThrow(`/clubs/${props.clubUlid}/drive-upload/refresh-token`, {
                method: 'POST',
                headers: jsonHeaders(),
                credentials: 'same-origin',
            });
            return await res.json();
        },

        async onProbeCompletion(sessionUri: string, totalSize: number) {
            const res = await fetchOrThrow(`${driveUploadBaseUrl.value}/probe`, {
                method: 'POST',
                headers: jsonHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({ session_uri: sessionUri, total_size: totalSize }),
            });
            return await res.json();
        },
    }, driveAbortController?.signal);
}

async function onDriveUploadComplete(driveFileId: string) {
    stopNavigationGuard();

    try {
        const res = await fetch(`${driveUploadBaseUrl.value}/complete`, {
            method: 'POST',
            headers: jsonHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({
                drive_file_id: driveFileId,
                upload_ulid: pendingDriveUpload.value?.uploadUlid,
            }),
        });

        if (!res.ok) {
            const data = await res.json();
            throw new Error(data.error || 'Error al registrar el video.');
        }

        status.value = 'encoding';
        pendingDriveUpload.value = null;
        startPolling();
    } catch (err: any) {
        status.value = 'failed';
        errorMessage.value = err.message || 'Error al registrar el video.';
    }
}

async function discardPendingUpload() {
    if (pendingDriveUpload.value) {
        await deletePendingUpload(props.matchUlid);
        pendingDriveUpload.value = null;
    }
    status.value = 'idle';
    errorMessage.value = '';
}

// ── Controls ───────────────────────────────────────────────────

function pauseUpload() {
    driveAbortController?.abort();
    driveAbortController = null;
    status.value = 'paused';
}

function unpauseUpload() {
    // For Drive, pause is equivalent to stopping — user must re-select file to resume
    status.value = 'resumable';
}

async function cancelUpload() {
    driveAbortController?.abort();
    driveAbortController = null;
    await deletePendingUpload(props.matchUlid);
    pendingDriveUpload.value = null;

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
        const url = new URL(videoUploadUrl.value, window.location.origin);
        url.searchParams.set('force', '1');

        await fetch(url.toString(), {
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

    if (data.video_upload) {
        videoData.value = data.video_upload;
    }

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

async function leavePageAnyway() {
    showUploadingWarning.value = false;

    driveAbortController?.abort();
    driveAbortController = null;

    stopNavigationGuard();

    if (pendingVisitUrl.value) {
        bypassNavigationGuard = true;
        router.visit(pendingVisitUrl.value);
        pendingVisitUrl.value = null;
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

onMounted(async () => {
    document.addEventListener('visibilitychange', onVisibilityChange);

    if (status.value === 'encoding') {
        startPolling();
    }

    // Check for pending Drive upload to resume
    if (status.value === 'idle') {
        try {
            const pending = await getPendingUpload(props.matchUlid);
            if (pending) {
                pendingDriveUpload.value = pending;
                uploadedFilename.value = pending.fileName;
                progress.value = Math.round((pending.bytesUploaded / pending.fileSize) * 100);
                status.value = 'resumable';
            }
        } catch {
            // IndexedDB not available — continue normally
        }
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', onVisibilityChange);
    stopPolling();
    stopNavigationGuard();
    driveAbortController?.abort();
    driveAbortController = null;
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

        <!-- Resumable: Pending Drive upload found -->
        <div v-else-if="status === 'resumable'" class="rounded-lg border border-blue-500/30 bg-blue-500/5 p-4">
            <div class="mb-2 flex items-center justify-between">
                <span class="text-sm font-medium text-blue-400">Subida pendiente</span>
                <span class="text-xs text-muted-foreground">{{ uploadedFilename }}</span>
            </div>

            <!-- Progress bar showing previous progress -->
            <div class="mb-2 h-2.5 w-full overflow-hidden rounded-full bg-secondary">
                <div class="h-full rounded-full bg-blue-500/50 transition-all duration-300" :style="{ width: `${progress}%` }" />
            </div>

            <p class="mb-3 text-xs text-muted-foreground">
                Progreso anterior: {{ progress }}% ({{ pendingDriveUpload ? formatBytes(pendingDriveUpload.bytesUploaded) : '' }} de
                {{ pendingDriveUpload ? formatBytes(pendingDriveUpload.fileSize) : '' }}).
                Selecciona el mismo archivo para continuar.
            </p>

            <p v-if="errorMessage" class="mb-2 text-xs text-red-400">{{ errorMessage }}</p>

            <div class="flex gap-2">
                <Button type="button" variant="outline" size="sm" class="gap-1.5" @click="selectFileForResume">
                    <RotateCcw class="size-3.5" />
                    Continuar subida
                </Button>
                <Button type="button" variant="ghost" size="sm" class="gap-1.5 text-destructive hover:text-destructive" @click="discardPendingUpload">
                    <X class="size-3.5" />
                    Descartar
                </Button>
            </div>
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

        <!-- Encoding with video available -->
        <div v-else-if="status === 'encoding' && videoData?.drive_stream_url" class="space-y-3">
            <DrivePlayer :stream-url="videoData.drive_stream_url" :match-ulid="props.matchUlid" />
            <div class="flex items-center gap-2 text-xs text-amber-400">
                <Loader2 class="size-3 animate-spin" />
                Video disponible. Generando version 720p para reels...
            </div>
        </div>

        <!-- Encoding without video -->
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

            <!-- YouTube player with advanced controls -->
            <YouTubePlayer v-if="videoData?.youtube_video_id" :video-id="videoData.youtube_video_id" :match-ulid="props.matchUlid" />

            <!-- Drive HTML5 player with sync -->
            <DrivePlayer v-else-if="videoData?.drive_stream_url" :stream-url="videoData.drive_stream_url" :match-ulid="props.matchUlid" />

            <!-- S3 fallback player -->
            <template v-else-if="props.s3VideoUrl">
                <VideoPlayer :src="props.s3VideoUrl" />
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
                        Se esta subiendo un video. Si sales de esta pagina podras retomar la subida despues seleccionando el mismo archivo.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <Button variant="outline" @click="leavePageAnyway">
                        Salir de la pagina
                    </Button>
                    <DialogClose as-child>
                        <Button>Continuar subida</Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete confirmation dialog -->
        <Dialog v-model:open="showDeleteConfirm">
            <DialogContent @pointer-down-outside.prevent @interact-outside.prevent>
                <DialogHeader>
                    <DialogTitle>Eliminar video del partido</DialogTitle>
                    <DialogDescription>
                        Se eliminara el video de este partido. Los reels generados se mantendran pero no se podran generar nuevos hasta subir otro video.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
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
