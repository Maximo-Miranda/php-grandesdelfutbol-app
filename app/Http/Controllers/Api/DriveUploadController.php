<?php

namespace App\Http\Controllers\Api;

use App\Actions\Video\StartVideoProcessingPipeline;
use App\Enums\VideoUploadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Match\InitDriveUploadRequest;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Services\GoogleAuthService;
use App\Services\GoogleDriveService;
use App\Support\GoogleDriveUrlParser;
use Google\Service\Drive;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DriveUploadController extends Controller
{
    /** @var array<int, int> Backoff delays (ms) between retries. */
    private const DRIVE_RETRY_BACKOFF_MS = [200, 1000, 3000];

    /** @var array<int, string> Reasons considered transient (worth retrying). */
    private const TRANSIENT_DRIVE_REASONS = ['rateLimitExceeded', 'userRateLimitExceeded', 'backendError'];

    public function __construct(
        private GoogleDriveService $driveService,
        private GoogleAuthService $authService,
        private StartVideoProcessingPipeline $startProcessingPipeline,
    ) {}

    /**
     * Initiate a Google Drive resumable upload session.
     *
     * Creates a club folder on Drive if needed, starts a resumable upload
     * session, and returns the session URI + access token for the frontend
     * to upload chunks directly to Google Drive.
     */
    public function initUpload(InitDriveUploadRequest $request, Club $club, FootballMatch $match): JsonResponse
    {
        $existingUpload = $match->videoUpload;

        if ($existingUpload) {
            if ($existingUpload->status === VideoUploadStatus::Uploading) {
                if ($existingUpload->drive_file_id) {
                    rescue(fn () => $this->driveService->deleteFile($existingUpload->drive_file_id));
                }
                $existingUpload->delete();
            } else {
                return response()->json(['error' => 'Este partido ya tiene un video.'], 422);
            }
        }

        $validated = $request->validated();

        $folderId = $this->driveService->ensureClubFolder($club);

        $fileName = "{$match->ulid}.".pathinfo($validated['filename'], PATHINFO_EXTENSION);

        $sessionUri = $this->driveService->createResumableSession(
            $fileName,
            $validated['content_type'],
            (int) $validated['filesize'],
            $folderId,
            $request->headers->get('Origin'),
        );

        $videoUpload = $match->videoUpload()->create([
            'uploaded_by' => $request->user()->id,
            'status' => VideoUploadStatus::Uploading,
            'original_filename' => $validated['filename'],
            'original_size_bytes' => $validated['filesize'],
            'uploaded_at' => now(),
        ]);

        $token = $this->authService->getAccessToken();

        Log::info('Drive upload session created', [
            'match' => $match->ulid,
            'club' => $club->ulid,
            'filename' => $fileName,
        ]);

        return response()->json([
            'session_uri' => $sessionUri,
            'access_token' => $token['access_token'],
            'expires_at' => $token['expires_at'],
            'upload_ulid' => $videoUpload->ulid,
        ]);
    }

    /** Return a fresh Google access token for the frontend to continue uploading. */
    public function refreshToken(Club $club): JsonResponse
    {
        Gate::authorize('viewAny', [FootballMatch::class, $club]);

        $token = $this->authService->getAccessToken();

        return response()->json([
            'access_token' => $token['access_token'],
            'expires_at' => $token['expires_at'],
        ]);
    }

    /**
     * Probe a resumable upload session to check progress.
     *
     * Done server-side to avoid CORS issues with the browser making
     * direct PUT requests to Google's upload endpoint.
     */
    public function probeStatus(Request $request, Club $club, FootballMatch $match): JsonResponse
    {
        Gate::authorize('update', $match);

        $validated = $request->validate([
            'session_uri' => ['required', 'string', 'url:https', 'starts_with:https://www.googleapis.com/'],
            'total_size' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $result = $this->driveService->probeUploadStatus(
                $validated['session_uri'],
                (int) $validated['total_size'],
            );

            $token = $this->authService->getAccessToken();

            return response()->json([
                ...$result,
                'access_token' => $token['access_token'],
                'expires_at' => $token['expires_at'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'expired' => str_contains($e->getMessage(), 'expirado'),
            ], 422);
        }
    }

    /**
     * Mark a Drive upload as complete and start the processing pipeline.
     *
     * Called by the frontend after all chunks have been uploaded to Google Drive.
     * Verifies the file exists on Drive, then dispatches encoding + YouTube upload.
     */
    public function completeUpload(Request $request, Club $club, FootballMatch $match): JsonResponse
    {
        Gate::authorize('update', $match);

        $validated = $request->validate([
            'drive_file_id' => ['required', 'string', 'max:200'],
            'upload_ulid' => ['required', 'string', 'max:26'],
        ]);

        $videoUpload = $match->videoUpload;

        if (! $videoUpload || $videoUpload->ulid !== $validated['upload_ulid']) {
            return response()->json(['error' => 'Upload no encontrado.'], 404);
        }

        $this->driveService->getFileMetadata($validated['drive_file_id']);

        $videoUpload->update([
            'drive_file_id' => $validated['drive_file_id'],
            'status' => VideoUploadStatus::Encoding,
        ]);

        rescue(function () use ($validated, $videoUpload) {
            $this->driveService->shareFilePublicly($validated['drive_file_id']);
            $videoUpload->update(['drive_shared_at' => now()]);
        });

        ($this->startProcessingPipeline)($videoUpload);

        Log::info('Drive upload completed, processing started', [
            'match' => $match->ulid,
            'drive_file_id' => $validated['drive_file_id'],
        ]);

        return response()->json([
            'video_upload' => $videoUpload->fresh(),
        ]);
    }

    /**
     * Import a video by pasting a public Google Drive link.
     *
     * Validates accessibility via files.get, copies to our folder via
     * server-side files.copy (no bytes flow through our server),
     * then dispatches the existing encoding pipeline.
     */
    public function fromLink(Request $request, Club $club, FootballMatch $match): RedirectResponse
    {
        Gate::authorize('update', $match);

        $validated = $request->validate([
            'drive_url' => ['required', 'string', 'url', 'max:500'],
        ]);

        $fileId = GoogleDriveUrlParser::extractFileId($validated['drive_url']);

        if (! $fileId) {
            return back()->withErrors([
                'drive_url' => 'Link de Google Drive inválido. Debe tener el formato https://drive.google.com/file/d/...',
            ]);
        }

        if (! $this->authService->hasScope(Drive::DRIVE_READONLY)) {
            return back()->withErrors([
                'drive_url' => 'La app necesita un permiso adicional de Google Drive para leer archivos públicos por link. Pedile al super-admin que reautorice la cuenta en /admin/youtube/authorize (se abrirá una pantalla de Google pidiendo el nuevo permiso).',
            ]);
        }

        $lock = Cache::lock("drive-import:match:{$match->id}", 120);

        if (! $lock->get()) {
            return back()->withErrors([
                'drive_url' => 'Ya hay una importación en curso para este partido. Esperá unos segundos e intentá de nuevo.',
            ]);
        }

        try {
            return $this->performDriveImport($request, $club, $match, $fileId);
        } finally {
            $lock->release();
        }
    }

    private function performDriveImport(Request $request, Club $club, FootballMatch $match, string $fileId): RedirectResponse
    {
        Log::info('drive.import.started', [
            'match_id' => $match->id,
            'user_id' => $request->user()->id,
            'source_file_id' => $fileId,
        ]);

        try {
            $metadata = $this->retryDriveCall(fn () => $this->driveService->getFileMetadata($fileId));
        } catch (GoogleServiceException $e) {
            $this->logDriveFailure('drive.import.metadata_failed', $e, $match->id, $request->user()->id, $fileId);

            return back()->withErrors(['drive_url' => $this->humanizeDriveError($e)]);
        } catch (\Throwable $e) {
            Log::error('drive.import.metadata_error', [
                'match_id' => $match->id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'drive_url' => 'Ocurrió un error al verificar el archivo. Intentá en unos minutos.',
            ]);
        }

        if (! Str::startsWith((string) ($metadata['mimeType'] ?? ''), 'video/')) {
            return back()->withErrors([
                'drive_url' => "El archivo no es un video (tipo detectado: {$metadata['mimeType']}).",
            ]);
        }

        $sizeBytes = (int) ($metadata['size'] ?? 0);

        if ($sizeBytes === 0) {
            return back()->withErrors([
                'drive_url' => 'No pudimos determinar el tamaño del video. Verificá que el link sea accesible.',
            ]);
        }

        $folderId = $this->driveService->ensureClubFolder($club);
        $extension = pathinfo($metadata['name'] ?? 'video.mp4', PATHINFO_EXTENSION) ?: 'mp4';
        $newName = "{$match->ulid}-original.{$extension}";

        try {
            $copiedFileId = $this->retryDriveCall(fn () => $this->driveService->copyFile($fileId, $newName, $folderId));
        } catch (GoogleServiceException $e) {
            $this->logDriveFailure('drive.import.copy_failed', $e, $match->id, $request->user()->id, $fileId);

            return back()->withErrors(['drive_url' => $this->humanizeDriveError($e)]);
        }

        $existing = $match->videoUpload;

        if ($existing?->drive_file_id && $existing->drive_file_id !== $copiedFileId) {
            rescue(fn () => $this->driveService->deleteFile($existing->drive_file_id));
        }

        $upload = $match->videoUpload()->updateOrCreate(
            ['football_match_id' => $match->id],
            [
                'ulid' => $existing?->ulid ?? (string) Str::ulid(),
                'uploaded_by' => $request->user()->id,
                'drive_file_id' => $copiedFileId,
                'original_filename' => $metadata['name'] ?? $newName,
                'original_size_bytes' => $sizeBytes,
                'status' => VideoUploadStatus::Encoding,
                'uploaded_at' => now(),
                'error_message' => null,
            ],
        );

        Log::info('drive.import.copied', [
            'match_id' => $match->id,
            'user_id' => $request->user()->id,
            'source_file_id' => $fileId,
            'copied_file_id' => $copiedFileId,
            'size_bytes' => $sizeBytes,
        ]);

        rescue(function () use ($copiedFileId, $upload) {
            $this->driveService->shareFilePublicly($copiedFileId);
            $upload->update(['drive_shared_at' => now()]);
        });

        ($this->startProcessingPipeline)($upload);

        return back()->with('success', 'Video guardado en nuestro Drive. Preparando para publicación...');
    }

    /** Run a Drive API call with exponential backoff on transient errors. */
    private function retryDriveCall(callable $call): mixed
    {
        return retry(
            self::DRIVE_RETRY_BACKOFF_MS,
            $call,
            0,
            fn (\Throwable $e) => $this->isTransientDriveError($e),
        );
    }

    /** Permission/not-found/auth errors are deterministic and not retried. */
    private function isTransientDriveError(\Throwable $e): bool
    {
        if (! $e instanceof GoogleServiceException) {
            return false;
        }

        $code = $e->getCode();

        if ($code >= 500 || $code === 429) {
            return true;
        }

        if ($code === 403) {
            $reason = $e->getErrors()[0]['reason'] ?? '';

            return in_array($reason, self::TRANSIENT_DRIVE_REASONS, true);
        }

        return false;
    }

    private function logDriveFailure(string $event, GoogleServiceException $e, int $matchId, int $userId, string $sourceFileId): void
    {
        Log::warning($event, [
            'match_id' => $matchId,
            'user_id' => $userId,
            'source_file_id' => $sourceFileId,
            'code' => $e->getCode(),
            'reason' => $e->getErrors()[0]['reason'] ?? null,
        ]);
    }

    /**
     * Map Google Drive API errors to user-friendly Spanish messages.
     *
     * @see https://developers.google.com/workspace/drive/api/guides/handle-errors
     */
    private function humanizeDriveError(GoogleServiceException $e): string
    {
        $reason = $e->getErrors()[0]['reason'] ?? '';
        $code = $e->getCode();

        $permissionReasons = ['insufficientFilePermissions', 'forbidden'];
        $rateLimitReasons = ['rateLimitExceeded', 'userRateLimitExceeded'];

        return match (true) {
            $code === 404 => 'Este link no es público. Abrí el archivo en Drive → Compartir → cambiá el acceso a "Cualquiera con el link" y pegá el link otra vez.',
            $code === 403 && $reason === 'cannotCopyFile' => 'El dueño del archivo no permite copiarlo. En Drive → Compartir → Configuración, activá "Los lectores pueden descargar, imprimir y copiar".',
            $code === 403 && in_array($reason, $permissionReasons, true) => 'El archivo está compartido solo con cuentas específicas. En Drive → Compartir, cambiá "Acceso general" a "Cualquiera con el link".',
            $code === 403 && in_array($reason, $rateLimitReasons, true) => 'Google Drive está recibiendo muchas solicitudes en este momento. Esperá un minuto y volvé a intentar.',
            $code === 401 => 'La autorización de Google expiró. Pedile al super-admin que reautorice la cuenta en /admin/youtube/authorize.',
            default => "Google Drive rechazó la solicitud (código {$code}). Asegurate de que el link sea de acceso público (Cualquiera con el link).",
        };
    }
}
