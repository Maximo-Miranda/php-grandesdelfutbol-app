/**
 * Google Drive Resumable Upload Client
 *
 * Handles chunked uploads directly from the browser to Google Drive
 * using the resumable upload protocol. Session URIs persist for 7 days,
 * and upload state is saved to IndexedDB for cross-session resume.
 *
 * @see https://developers.google.com/workspace/drive/api/guides/manage-uploads#resumable
 */

const DB_NAME = 'gdf-drive-uploads';
const DB_VERSION = 1;
const STORE_NAME = 'pending-uploads';
const CHUNK_SIZE = 10 * 1024 * 1024; // 10MB (multiple of 256KB as required by Google)
const TOKEN_REFRESH_MARGIN = 5 * 60; // Refresh token 5 minutes before expiry
const MAX_RETRIES = 10;
const BASE_RETRY_DELAY = 1000; // 1 second

export interface PendingUpload {
    matchUlid: string;
    sessionUri: string;
    accessToken: string;
    expiresAt: number;
    fileName: string;
    fileSize: number;
    fileType: string;
    bytesUploaded: number;
    uploadUlid: string;
    createdAt: number;
}

export interface DriveUploaderCallbacks {
    onProgress: (bytesUploaded: number, totalBytes: number) => void;
    onComplete: (driveFileId: string) => void;
    onError: (error: Error) => void;
    onTokenRefresh: () => Promise<{ access_token: string; expires_at: number }>;
    onProbeCompletion: (sessionUri: string, totalSize: number) => Promise<{ complete: boolean; drive_file_id: string | null; bytes_uploaded: number }>;
}

// ── IndexedDB helpers ──────────────────────────────────────────────

function openDb(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = () => {
            const db = request.result;
            if (!db.objectStoreNames.contains(STORE_NAME)) {
                db.createObjectStore(STORE_NAME, { keyPath: 'matchUlid' });
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function withStore<T>(
    mode: IDBTransactionMode,
    action: (store: IDBObjectStore) => IDBRequest | void,
): Promise<T> {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_NAME, mode);
        const result = action(tx.objectStore(STORE_NAME));
        tx.oncomplete = () => resolve(result ? result.result : undefined as T);
        tx.onerror = () => reject(tx.error);
    });
}

export function savePendingUpload(upload: PendingUpload): Promise<void> {
    return withStore('readwrite', (store) => store.put({ ...upload }));
}

export function getPendingUpload(matchUlid: string): Promise<PendingUpload | undefined> {
    return withStore('readonly', (store) => store.get(matchUlid));
}

export function deletePendingUpload(matchUlid: string): Promise<void> {
    return withStore('readwrite', (store) => store.delete(matchUlid));
}

// ── Range header parsing ─────────────────────────────────────────

/** Extract the last byte position from a `Range: bytes=0-N` header. */
function parseRangeEnd(rangeHeader: string | null): number | null {
    const match = rangeHeader?.match(/bytes=\d+-(\d+)/);
    return match ? parseInt(match[1], 10) : null;
}

// ── Upload engine ──────────────────────────────────────────────────

/**
 * Upload a chunk to Google Drive via the resumable session URI.
 *
 * Uses XMLHttpRequest instead of fetch for reliable access to response
 * headers (Range, Location) in CORS scenarios.
 *
 * @see https://developers.google.com/workspace/drive/api/guides/manage-uploads#uploading
 */
function uploadChunk(
    sessionUri: string,
    accessToken: string,
    chunk: Blob,
    start: number,
    end: number,
    totalSize: number,
    signal?: AbortSignal,
): Promise<{ status: number; responseText: string; rangeEnd: number }> {
    return new Promise((resolve, reject) => {
        if (signal?.aborted) {
            reject(new DOMException('Upload aborted', 'AbortError'));
            return;
        }

        const xhr = new XMLHttpRequest();
        xhr.open('PUT', sessionUri);
        xhr.setRequestHeader('Authorization', `Bearer ${accessToken}`);
        xhr.setRequestHeader('Content-Range', `bytes ${start}-${end}/${totalSize}`);

        xhr.onload = () => {
            const rangeEnd = (xhr.status === 308)
                ? parseRangeEnd(xhr.getResponseHeader('Range')) ?? end
                : end;
            resolve({ status: xhr.status, responseText: xhr.responseText, rangeEnd });
        };

        xhr.onerror = () => reject(new Error('Error de red durante la subida.'));
        xhr.ontimeout = () => reject(new Error('Timeout durante la subida del chunk.'));
        xhr.timeout = 120000; // 2 minutes per chunk

        signal?.addEventListener('abort', () => xhr.abort(), { once: true });

        xhr.send(chunk);
    });
}

/**
 * Probe Google Drive to determine how many bytes have been uploaded.
 *
 * @see https://developers.google.com/workspace/drive/api/guides/manage-uploads#resuming
 * @returns The last confirmed byte, or -1 if no bytes uploaded, or the file ID if complete
 */
export async function probeUploadStatus(
    sessionUri: string,
    accessToken: string,
    totalSize: number,
): Promise<{ complete: boolean; driveFileId?: string; bytesUploaded: number }> {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('PUT', sessionUri);
        xhr.setRequestHeader('Authorization', `Bearer ${accessToken}`);
        xhr.setRequestHeader('Content-Range', `*/${totalSize}`);

        xhr.onload = () => {
            if (xhr.status === 200 || xhr.status === 201) {
                try {
                    const data = JSON.parse(xhr.responseText);
                    resolve({ complete: true, driveFileId: data.id, bytesUploaded: totalSize });
                } catch {
                    resolve({ complete: true, bytesUploaded: totalSize });
                }
            } else if (xhr.status === 308) {
                const lastByte = parseRangeEnd(xhr.getResponseHeader('Range'));
                // No Range header means no bytes received yet
                resolve({ complete: false, bytesUploaded: lastByte !== null ? lastByte + 1 : 0 });
            } else if (xhr.status === 404) {
                reject(new Error('La sesión de subida ha expirado. Debes iniciar la subida de nuevo.'));
            } else {
                reject(new Error(`Error al verificar el estado de la subida: ${xhr.status}`));
            }
        };

        xhr.onerror = () => reject(new Error('Error de red al verificar estado de subida.'));
        xhr.send();
    });
}

/**
 * Execute the resumable upload loop.
 *
 * Uploads a file in chunks, persisting progress to IndexedDB after each
 * successful chunk. Handles token refresh and retries with exponential backoff.
 *
 * Pass an AbortSignal to cancel the upload cleanly. The current chunk XHR
 * will be aborted and progress saved to IndexedDB for later resume.
 */
export async function executeUpload(
    file: File,
    pendingUpload: PendingUpload,
    callbacks: DriveUploaderCallbacks,
    signal?: AbortSignal,
): Promise<void> {
    let { bytesUploaded, accessToken, expiresAt, sessionUri } = pendingUpload;
    const { fileSize } = pendingUpload;

    while (bytesUploaded < fileSize) {
        if (signal?.aborted) return;

        // Refresh token if close to expiry
        if (Date.now() / 1000 > expiresAt - TOKEN_REFRESH_MARGIN) {
            try {
                const refreshed = await callbacks.onTokenRefresh();
                accessToken = refreshed.access_token;
                expiresAt = refreshed.expires_at;

                pendingUpload.accessToken = accessToken;
                pendingUpload.expiresAt = expiresAt;
                await savePendingUpload(pendingUpload);
            } catch (err) {
                callbacks.onError(new Error('No se pudo renovar el token de acceso.'));
                return;
            }
        }

        const start = bytesUploaded;
        const end = Math.min(start + CHUNK_SIZE - 1, fileSize - 1);
        const chunk = file.slice(start, end + 1);

        let success = false;
        let retries = 0;

        while (!success && retries < MAX_RETRIES) {
            try {
                const result = await uploadChunk(sessionUri, accessToken, chunk, start, end, fileSize, signal);

                if (result.status === 200 || result.status === 201) {
                    // Upload complete
                    await deletePendingUpload(pendingUpload.matchUlid);
                    callbacks.onProgress(fileSize, fileSize);

                    try {
                        const data = JSON.parse(result.responseText);
                        callbacks.onComplete(data.id);
                    } catch {
                        callbacks.onError(new Error('Subida completa pero no se pudo obtener el ID del archivo.'));
                    }
                    return;
                }

                if (result.status === 308) {
                    bytesUploaded = result.rangeEnd + 1;
                    pendingUpload.bytesUploaded = bytesUploaded;
                    await savePendingUpload(pendingUpload);
                    callbacks.onProgress(bytesUploaded, fileSize);
                    success = true;
                } else if (result.status === 401) {
                    // Token expired mid-upload, refresh and retry
                    const refreshed = await callbacks.onTokenRefresh();
                    accessToken = refreshed.access_token;
                    expiresAt = refreshed.expires_at;
                    pendingUpload.accessToken = accessToken;
                    pendingUpload.expiresAt = expiresAt;
                    await savePendingUpload(pendingUpload);
                    retries++;
                } else if (result.status === 404) {
                    await deletePendingUpload(pendingUpload.matchUlid);
                    callbacks.onError(new Error('La sesión de subida ha expirado. Debes iniciar la subida de nuevo.'));
                    return;
                } else if (result.status >= 500) {
                    retries++;
                    await sleep(retryDelay(retries));
                } else {
                    callbacks.onError(new Error(`Error inesperado: HTTP ${result.status}`));
                    return;
                }
            } catch (err) {
                if (err instanceof DOMException && err.name === 'AbortError') return;

                // Google Drive doesn't send CORS headers on the final 200/201
                // response. When we're on the last chunk, a network error likely
                // means the upload completed but the browser blocked the response.
                // Fall back to the backend probe to verify completion.
                const isLastChunk = end >= fileSize - 1;
                if (isLastChunk) {
                    try {
                        const probe = await callbacks.onProbeCompletion(sessionUri, fileSize);
                        if (probe.complete && probe.drive_file_id) {
                            await deletePendingUpload(pendingUpload.matchUlid);
                            callbacks.onProgress(fileSize, fileSize);
                            callbacks.onComplete(probe.drive_file_id);
                            return;
                        }
                    } catch {
                        // Probe failed — fall through to normal retry
                    }
                }

                retries++;
                if (retries >= MAX_RETRIES) {
                    callbacks.onError(new Error('Se agotaron los reintentos. Verifica tu conexión e intenta de nuevo.'));
                    return;
                }
                await sleep(retryDelay(retries));
            }
        }

        if (!success) {
            callbacks.onError(new Error('No se pudo subir el chunk después de múltiples intentos.'));
            return;
        }
    }
}

function retryDelay(attempt: number): number {
    return Math.min(BASE_RETRY_DELAY * Math.pow(2, attempt - 1), 60000);
}

function sleep(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
}
