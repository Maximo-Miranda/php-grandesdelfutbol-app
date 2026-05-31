<?php

namespace App\Jobs;

use App\Enums\VideoUploadStatus;
use App\Models\MatchVideoUpload;
use App\Services\YouTubeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class WaitForYouTubeProcessing implements ShouldQueue
{
    use Queueable;

    public int $tries = 30;

    public int $timeout = 120;

    /** @var array<int, int> */
    public array $backoff = [120];

    public function __construct(
        public MatchVideoUpload $videoUpload,
    ) {
        $this->onQueue('youtube');
    }

    public function handle(YouTubeService $youtubeService): void
    {
        if (! $this->videoUpload->youtube_video_id) {
            return;
        }

        if ($this->videoUpload->status === VideoUploadStatus::Ready) {
            return;
        }

        $status = $youtubeService->getProcessingStatus($this->videoUpload->youtube_video_id);

        Log::info("YouTube processing status for {$this->videoUpload->youtube_video_id}: {$status}");

        if ($status === 'succeeded') {
            MatchVideoUpload::query()
                ->where('id', $this->videoUpload->id)
                ->where('status', '!=', VideoUploadStatus::Ready)
                ->update([
                    'status' => VideoUploadStatus::Ready,
                    'encoded_at' => now(),
                    'error_message' => null,
                ]);

            return;
        }

        if (in_array($status, ['failed', 'terminated'])) {
            $this->videoUpload->update([
                'status' => VideoUploadStatus::Failed,
                'error_message' => 'El procesamiento del video en YouTube falló. Puedes reintentar la subida.',
            ]);

            return;
        }

        $this->release(120);
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);
    }
}
