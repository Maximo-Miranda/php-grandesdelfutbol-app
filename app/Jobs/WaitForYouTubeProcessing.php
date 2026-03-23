<?php

namespace App\Jobs;

use App\Enums\VideoUploadStatus;
use App\Models\MatchVideoUpload;
use App\Notifications\MatchVideoUploadedNotification;
use App\Services\YouTubeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class WaitForYouTubeProcessing implements ShouldQueue
{
    use Queueable;

    public int $tries = 30;

    /** @var array<int, int> */
    public array $backoff = [120];

    public function __construct(
        public MatchVideoUpload $videoUpload,
    ) {
        $this->onQueue('video-processing');
    }

    public function handle(YouTubeService $youtubeService): void
    {
        if (! $this->videoUpload->youtube_video_id) {
            return;
        }

        // Already ready — nothing to do
        if ($this->videoUpload->status === VideoUploadStatus::Ready) {
            return;
        }

        $status = $youtubeService->getProcessingStatus($this->videoUpload->youtube_video_id);

        Log::info("YouTube processing status for {$this->videoUpload->youtube_video_id}: {$status}");

        if ($status === 'succeeded') {
            $this->videoUpload->update([
                'status' => VideoUploadStatus::Ready,
                'encoded_at' => now(),
                'error_message' => null,
            ]);

            $this->notifyClub();

            return;
        }

        if (in_array($status, ['failed', 'terminated'])) {
            $this->videoUpload->update([
                'error_message' => "YouTube processing {$status}.",
            ]);

            return;
        }

        $this->release(120);
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);
    }

    private function notifyClub(): void
    {
        $match = $this->videoUpload->match;
        $club = $match?->club;

        if (! $club) {
            return;
        }

        $notification = new MatchVideoUploadedNotification($match);

        Notification::send($club->approvedMemberUsersWithPush(), $notification);

        PublishClubNtfy::dispatch($club, $notification->toNtfyPayload());
    }
}
