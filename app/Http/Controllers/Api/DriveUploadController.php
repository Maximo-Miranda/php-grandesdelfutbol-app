<?php

namespace App\Http\Controllers\Api;

use App\Enums\VideoUploadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Match\InitDriveUploadRequest;
use App\Jobs\ProcessUploadedVideo;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Services\GoogleAuthService;
use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class DriveUploadController extends Controller
{
    public function __construct(
        private GoogleDriveService $driveService,
        private GoogleAuthService $authService,
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

        ProcessUploadedVideo::dispatch($videoUpload);

        Log::info('Drive upload completed, processing started', [
            'match' => $match->ulid,
            'drive_file_id' => $validated['drive_file_id'],
        ]);

        return response()->json([
            'video_upload' => $videoUpload->fresh(),
        ]);
    }
}
