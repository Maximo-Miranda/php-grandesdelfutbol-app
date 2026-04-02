<?php

namespace App\Http\Controllers;

use App\Jobs\UploadMatchToYouTube;
use App\Jobs\WaitForYouTubeProcessing;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Services\GoogleDriveService;
use App\Services\YouTubeQuotaService;
use App\Services\YouTubeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class MatchVideoUploadController extends Controller
{
    public function __construct(
        private YouTubeService $youtubeService,
        private GoogleDriveService $driveService,
    ) {}

    public function show(Club $club, FootballMatch $match): JsonResponse
    {
        return response()->json([
            'video_upload' => $match->videoUpload,
        ]);
    }

    /** Retry YouTube upload for a video that already has S3 encoding but failed YouTube. */
    public function retryYouTube(Club $club, FootballMatch $match, YouTubeQuotaService $quotaService): JsonResponse
    {
        Gate::authorize('superAdmin');

        $videoUpload = $match->videoUpload;

        if (! $videoUpload || ! $videoUpload->s3_path) {
            return response()->json(['error' => 'No hay video disponible para subir.'], 422);
        }

        if (! $quotaService->isQuotaAvailable()) {
            return response()->json([
                'error' => "Límite diario de YouTube alcanzado ({$quotaService->quotaLabel()}). Intenta mañana.",
            ], 429);
        }

        $videoUpload->update([
            'youtube_video_id' => null,
            'youtube_uploaded_at' => null,
            'youtube_upload_requested_at' => now(),
            'error_message' => null,
        ]);

        UploadMatchToYouTube::dispatch($videoUpload)
            ->chain([new WaitForYouTubeProcessing($videoUpload)]);

        return response()->json(['message' => 'Reintentando subida a YouTube.']);
    }

    public function destroy(Club $club, FootballMatch $match): JsonResponse
    {
        Gate::authorize('update', $match);

        $videoUpload = $match->videoUpload;

        if (! $videoUpload) {
            return response()->json(['error' => 'No hay video para eliminar.'], 404);
        }

        if ($videoUpload->youtube_video_id) {
            rescue(fn () => $this->youtubeService->deleteVideo($videoUpload->youtube_video_id));
        }

        if ($videoUpload->drive_file_id) {
            rescue(fn () => $this->driveService->deleteFile($videoUpload->drive_file_id));
        }
        if ($videoUpload->drive_reels_file_id) {
            rescue(fn () => $this->driveService->deleteFile($videoUpload->drive_reels_file_id));
        }

        foreach (array_filter([$videoUpload->s3_path, $videoUpload->original_s3_path, $videoUpload->s3_reels_path]) as $path) {
            Storage::disk('s3')->delete($path);
        }

        $videoUpload->delete();

        return response()->json(['message' => 'Video eliminado.']);
    }
}
