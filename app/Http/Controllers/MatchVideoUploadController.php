<?php

namespace App\Http\Controllers;

use App\Enums\VideoUploadStatus;
use App\Http\Requests\Match\StoreMatchVideoUploadRequest;
use App\Jobs\ProcessUploadedVideo;
use App\Jobs\UploadMatchToYouTube;
use App\Jobs\WaitForYouTubeProcessing;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Services\YouTubeQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class MatchVideoUploadController extends Controller
{
    /** Called after S3 multipart upload completes — registers the upload and dispatches pipeline. */
    public function store(StoreMatchVideoUploadRequest $request, Club $club, FootballMatch $match): JsonResponse
    {
        if ($match->videoUpload()->exists()) {
            return response()->json(['error' => 'Este partido ya tiene un video.'], 422);
        }

        $validated = $request->validated();

        $videoUpload = $match->videoUpload()->create([
            'uploaded_by' => $request->user()->id,
            'status' => VideoUploadStatus::Encoding,
            'original_filename' => $validated['filename'],
            'original_size_bytes' => $validated['filesize'],
            's3_path' => $validated['s3_key'],
            'uploaded_at' => now(),
        ]);

        // Dispatch the processing pipeline (FFmpeg encode → YouTube upload → reels)
        ProcessUploadedVideo::dispatch($videoUpload);

        return response()->json([
            'video_upload' => $videoUpload,
        ]);
    }

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

        if (! $videoUpload->youtube_video_id && ! request()->boolean('force')) {
            return response()->json([
                'warning' => 'Este video no se ha subido a YouTube. Si lo eliminas, se perderá permanentemente.',
                'requires_force' => true,
            ], 409);
        }

        if ($videoUpload->s3_path) {
            Storage::disk('s3')->delete($videoUpload->s3_path);
        }

        if ($videoUpload->original_s3_path) {
            Storage::disk('s3')->delete($videoUpload->original_s3_path);
        }

        $videoUpload->delete();

        return response()->json(['message' => 'Video eliminado.']);
    }
}
