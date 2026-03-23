<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import Uppy from '@uppy/core';
import Tus from '@uppy/tus';
import { AlertTriangle, CheckCircle, CloudUpload, Loader2, Pause, Play, Trash2, Upload, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
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

type VideoUploadData = {
    ulid: string;
    bunny_video_id: string;
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

function startNavigationGuard() {
    window.addEventListener('beforeunload', onBeforeUnloadHandler);
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
    if (removeInertiaListener) {
        removeInertiaListener();
        removeInertiaListener = null;
    }
}
const uploadedFilename = ref(props.existingUpload?.original_filename ?? '');

let uppy: Uppy | null = null;
let pollInterval: ReturnType<typeof setInterval> | null = null;
let lastLoaded = 0;
let lastTime = 0;

const storageKey = computed(() => `video-upload-${props.matchUlid}`);

const statusLabel = computed(() => {
    switch (status.value) {
        case 'idle': return 'Sin video';
        case 'uploading': return 'Subiendo...';
        case 'paused': return 'Pausado';
        case 'encoding': return 'Procesando video...';
        case 'ready': return 'Video listo';
        case 'failed': return 'Error';
        default: return '';
    }
});

const statusColor = computed(() => {
    switch (status.value) {
        case 'idle': return 'text-muted-foreground';
        case 'uploading': return 'text-blue-400';
        case 'paused': return 'text-yellow-400';
        case 'encoding': return 'text-amber-400';
        case 'ready': return 'text-emerald-400';
        case 'failed': return 'text-red-400';
        default: return '';
    }
});

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
            await fetch(`/clubs/${props.clubUlid}/matches/${props.matchUlid}/video-upload`, {
                method: 'DELETE',
                headers: { 'X-XSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json' },
                credentials: 'same-origin',
            }).catch(() => {});
        }

        const response = await fetch(`/clubs/${props.clubUlid}/matches/${props.matchUlid}/video-upload`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                filename: file.name,
                filesize: file.size,
            }),
        });

        if (!response.ok) {
            const data = await response.json();
            throw new Error(data.error || 'Error al iniciar la subida.');
        }

        const data = await response.json();

        const tusHeaders = {
            AuthorizationSignature: data.auth_signature,
            AuthorizationExpire: String(data.auth_expire),
            VideoId: data.video_id,
            LibraryId: data.library_id,
        };

        // Save TUS headers for resuming after page reload
        localStorage.setItem(storageKey.value, JSON.stringify(tusHeaders));

        initUppy(data.upload_url, file, tusHeaders);
    } catch (err: any) {
        status.value = 'failed';
        errorMessage.value = err.message || 'Error al iniciar la subida.';
    }
}

function initUppy(uploadUrl: string, file: File, tusHeaders: Record<string, string>) {
    uppy = new Uppy({
        restrictions: {
            maxFileSize: 25 * 1073741824,
            allowedFileTypes: ['video/*'],
            maxNumberOfFiles: 1,
        },
        autoProceed: true,
    });

    uppy.use(Tus, {
        endpoint: uploadUrl,
        chunkSize: 5 * 1048576,
        retryDelays: [0, 1000, 3000, 5000, 10000, 20000],
        storeFingerprintForResuming: true,
        removeFingerprintOnSuccess: true,
        headers: tusHeaders,
        metadata: {
            filetype: file.type || 'video/mp4',
            title: file.name,
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
        status.value = 'encoding';
        localStorage.removeItem(storageKey.value);
        stopNavigationGuard();

        // Notify backend that TUS upload finished
        try {
            await fetch(`/clubs/${props.clubUlid}/matches/${props.matchUlid}/video-upload/mark-encoding`, {
                method: 'POST',
                headers: { 'X-XSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
        } catch {
            // Non-critical — polling will still work
        }

        startPolling();
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
        (uppy.getPlugin('Tus') as any)?.pauseAll();
        status.value = 'paused';
    }
}

function unpauseUpload() {
    if (uppy) {
        (uppy.getPlugin('Tus') as any)?.resumeAll();
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
    localStorage.removeItem(storageKey.value);

    try {
        await fetch(`/clubs/${props.clubUlid}/matches/${props.matchUlid}/video-upload`, {
            method: 'DELETE',
            headers: {
                'X-XSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        });
    } catch {
        // Ignore cleanup errors
    }
}

async function deleteVideo() {
    try {
        await fetch(`/clubs/${props.clubUlid}/matches/${props.matchUlid}/video-upload`, {
            method: 'DELETE',
            headers: {
                'X-XSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        });
        showDeleteConfirm.value = false;
        status.value = 'idle';
        uploadedFilename.value = '';
    } catch {
        errorMessage.value = 'Error al eliminar el video.';
    }
}

function startPolling() {
    stopPolling();
    pollInterval = setInterval(async () => {
        try {
            const response = await fetch(
                `/clubs/${props.clubUlid}/matches/${props.matchUlid}/video-upload`,
                { credentials: 'same-origin', headers: { 'Accept': 'application/json' } },
            );
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

function getCsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find(row => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}

function onBeforeUnloadHandler(e: BeforeUnloadEvent) {
    e.preventDefault();
}

onMounted(async () => {
    if (status.value === 'encoding') {
        startPolling();
    }

    // If DB says "uploading", check with backend if Bunny actually received it
    if (props.existingUpload?.status === 'uploading') {
        try {
            const res = await fetch(
                `/clubs/${props.clubUlid}/matches/${props.matchUlid}/video-upload/check`,
                { credentials: 'same-origin', headers: { 'Accept': 'application/json' } },
            );
            const data = await res.json();
            if (data.received) {
                status.value = 'encoding';
                startPolling();
            }
            // If not received, status stays 'idle' — user can upload again
        } catch {
            // Stay idle
        }
    }
});

onBeforeUnmount(() => {
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
                <div>
                    <p class="text-sm font-medium text-amber-400">Procesando video...</p>
                    <p class="text-xs text-muted-foreground">Tu video se esta preparando para reproduccion. Esto puede tomar unos minutos.</p>
                </div>
            </div>
        </div>

        <!-- Ready -->
        <div v-else-if="status === 'ready'" class="space-y-3">
            <div class="flex items-center gap-2 text-sm text-emerald-400">
                <CheckCircle class="size-4" />
                <span class="font-medium">Video listo</span>
                <span class="text-xs text-muted-foreground">{{ uploadedFilename }}</span>
            </div>

            <!-- YouTube embed (preferred) -->
            <div v-if="props.existingUpload?.youtube_embed_url" class="aspect-video w-full overflow-hidden rounded-lg border border-border">
                <iframe
                    :src="props.existingUpload.youtube_embed_url"
                    class="h-full w-full"
                    allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen
                />
            </div>
            <!-- Bunny embed (fallback during YouTube processing) -->
            <div v-else-if="embedUrl" class="aspect-video w-full overflow-hidden rounded-lg border border-border">
                <iframe
                    :src="`${embedUrl}?autoplay=false&preload=false`"
                    class="h-full w-full"
                    allow="accelerometer; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen
                />
                <div class="mt-1 flex items-center gap-1.5 text-xs text-amber-400">
                    <Loader2 class="size-3 animate-spin" />
                    Procesando para YouTube...
                </div>
            </div>

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
            <div class="mt-3 flex gap-2">
                <Button type="button" variant="outline" size="sm" class="gap-1.5" @click="cancelUpload(); selectFile();">
                    <Upload class="size-3.5" />
                    Reintentar
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
