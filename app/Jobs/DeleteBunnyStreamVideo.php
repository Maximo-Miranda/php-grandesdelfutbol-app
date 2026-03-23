<?php

namespace App\Jobs;

use App\Models\MatchVideoUpload;
use App\Services\BunnyStreamService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class DeleteBunnyStreamVideo implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 120, 300];

    public function __construct(
        public MatchVideoUpload $videoUpload,
    ) {
        $this->onQueue('default');
    }

    public function handle(BunnyStreamService $bunnyService): void
    {
        if ($this->videoUpload->bunny_deleted_at) {
            return;
        }

        $bunnyService->deleteVideo($this->videoUpload->bunny_video_id);

        $this->videoUpload->update([
            'bunny_deleted_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        // Non-critical: Bunny still has the video but it doesn't affect anything.
        report($exception);
    }
}
