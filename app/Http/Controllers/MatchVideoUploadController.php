<?php

namespace App\Http\Controllers;

use App\Enums\VideoUploadStatus;
use App\Http\Requests\Match\StoreMatchVideoUploadRequest;
use App\Jobs\ProcessUploadedVideo;
use App\Models\Club;
use App\Models\FootballMatch;
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

    public function destroy(Club $club, FootballMatch $match): JsonResponse
    {
        Gate::authorize('update', $match);

        $videoUpload = $match->videoUpload;

        if (! $videoUpload) {
            return response()->json(['error' => 'No hay video para eliminar.'], 404);
        }

        // Clean up S3 files
        if ($videoUpload->s3_path) {
            Storage::disk('s3')->delete($videoUpload->s3_path);
        }

        $videoUpload->delete();

        return response()->json(['message' => 'Video eliminado.']);
    }
}
