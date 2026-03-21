<?php

namespace App\Http\Controllers\Api;

use App\Enums\VideoUploadStatus;
use App\Http\Controllers\Controller;
use App\Jobs\PublishClubNtfy;
use App\Models\MatchVideoUpload;
use App\Notifications\MatchVideoUploadedNotification;
use App\Services\BunnyStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class BunnyWebhookController extends Controller
{
    public function __construct(private BunnyStreamService $bunnyService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $videoId = $request->input('VideoGuid');

        if (! $videoId) {
            return response()->json(['error' => 'Missing VideoGuid'], 400);
        }

        $videoUpload = MatchVideoUpload::where('bunny_video_id', $videoId)->first();

        if (! $videoUpload) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        $status = (int) $request->input('Status');

        // Bunny Stream webhook status: 3 = Finished encoding, 4 = All resolutions ready, 5 = Failed
        if ($status === 4) {
            $this->handleEncodingSuccess($videoUpload);
        } elseif ($status === 5) {
            $this->handleEncodingFailure($videoUpload);
        }

        return response()->json(['ok' => true]);
    }

    private function handleEncodingSuccess(MatchVideoUpload $videoUpload): void
    {
        if ($videoUpload->status === VideoUploadStatus::Ready) {
            return;
        }

        try {
            $videoData = $this->bunnyService->getVideo($videoUpload->bunny_video_id);
            $duration = (int) ($videoData['length'] ?? 0);
        } catch (\Throwable) {
            $duration = 0;
        }

        $videoUpload->update([
            'status' => VideoUploadStatus::Ready,
            'duration_seconds' => $duration > 0 ? $duration : null,
            'encoded_at' => now(),
            'error_message' => null,
        ]);

        $match = $videoUpload->match;
        $club = $match?->club;

        if (! $club) {
            return;
        }

        $notification = new MatchVideoUploadedNotification($match);

        Notification::send($club->approvedMemberUsersWithPush(), $notification);

        PublishClubNtfy::dispatch($club, $notification->toNtfyPayload());
    }

    private function handleEncodingFailure(MatchVideoUpload $videoUpload): void
    {
        $videoUpload->update([
            'status' => VideoUploadStatus::Failed,
            'error_message' => 'El procesamiento del video falló en Bunny Stream.',
        ]);
    }
}
