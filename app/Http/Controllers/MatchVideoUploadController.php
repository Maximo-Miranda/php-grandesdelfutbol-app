<?php

namespace App\Http\Controllers;

use App\Enums\VideoUploadStatus;
use App\Http\Requests\Match\StoreMatchVideoUploadRequest;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Services\BunnyStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class MatchVideoUploadController extends Controller
{
    public function __construct(private BunnyStreamService $bunnyService) {}

    public function store(StoreMatchVideoUploadRequest $request, Club $club, FootballMatch $match): JsonResponse
    {
        if ($match->videoUpload()->exists()) {
            return response()->json(['error' => 'Este partido ya tiene un video.'], 422);
        }

        $bunnyVideo = $this->bunnyService->createVideo($match->title);
        $tusData = $this->bunnyService->getTusUploadUrl($bunnyVideo['guid']);

        $videoUpload = $match->videoUpload()->create([
            'uploaded_by' => $request->user()->id,
            'bunny_video_id' => $bunnyVideo['guid'],
            'status' => VideoUploadStatus::Uploading,
            'original_filename' => $request->input('filename'),
            'original_size_bytes' => $request->input('filesize'),
            'uploaded_at' => now(),
        ]);

        return response()->json([
            'video_upload' => $videoUpload,
            'upload_url' => $tusData['upload_url'],
            'auth_signature' => $tusData['auth_signature'],
            'auth_expire' => $tusData['auth_expire'],
            'library_id' => $tusData['library_id'],
            'video_id' => $tusData['video_id'],
        ]);
    }

    public function markEncoding(Club $club, FootballMatch $match): JsonResponse
    {
        $videoUpload = $match->videoUpload;

        if (! $videoUpload || $videoUpload->status !== VideoUploadStatus::Uploading) {
            return response()->json(['ok' => false], 422);
        }

        $videoUpload->update(['status' => VideoUploadStatus::Encoding]);

        return response()->json(['ok' => true]);
    }

    public function check(Club $club, FootballMatch $match): JsonResponse
    {
        $videoUpload = $match->videoUpload;

        if (! $videoUpload || $videoUpload->status !== VideoUploadStatus::Uploading) {
            return response()->json(['received' => false]);
        }

        try {
            $bunnyVideo = $this->bunnyService->getVideo($videoUpload->bunny_video_id);
            $bunnyStatus = (int) ($bunnyVideo['status'] ?? 0);

            if ($bunnyStatus > 0) {
                $videoUpload->update(['status' => VideoUploadStatus::Encoding]);

                return response()->json(['received' => true]);
            }
        } catch (\Throwable) {
            // If Bunny API fails, assume not received
        }

        return response()->json(['received' => false]);
    }

    public function show(Club $club, FootballMatch $match): JsonResponse
    {
        $videoUpload = $match->videoUpload;

        if (! $videoUpload) {
            return response()->json(['video_upload' => null]);
        }

        return response()->json([
            'video_upload' => $videoUpload,
            'stream_url' => $videoUpload->stream_url,
            'embed_url' => $videoUpload->embed_url,
            'thumbnail_url' => $videoUpload->thumbnail_url,
        ]);
    }

    public function destroy(Club $club, FootballMatch $match): JsonResponse
    {
        Gate::authorize('update', $match);

        $videoUpload = $match->videoUpload;

        if (! $videoUpload) {
            return response()->json(['error' => 'No hay video para eliminar.'], 404);
        }

        try {
            $this->bunnyService->deleteVideo($videoUpload->bunny_video_id);
        } catch (\Throwable) {
            // If Bunny deletion fails, still remove the local record
        }

        $videoUpload->delete();

        return response()->json(['message' => 'Video eliminado.']);
    }
}
