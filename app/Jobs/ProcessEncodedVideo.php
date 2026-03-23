<?php

namespace App\Jobs;

use App\Enums\VideoUploadStatus;
use App\Models\MatchVideoUpload;
use App\Services\BunnyStreamService;
use App\Services\ReelService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessEncodedVideo implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 2;

    public function __construct(
        public MatchVideoUpload $videoUpload,
    ) {
        $this->onQueue('video-processing');
    }

    public function handle(BunnyStreamService $bunnyService, ReelService $reelService): void
    {
        if ($this->videoUpload->status !== VideoUploadStatus::Ready) {
            return;
        }

        $match = $this->videoUpload->match;

        if (! $match) {
            return;
        }

        // Step 1: Download 720p to S3 for reels (if not already there)
        if (! $this->videoUpload->s3_path) {
            $this->downloadToS3($bunnyService, $match);
        }

        // Step 2: Build batch jobs
        $batchJobs = [];

        // Always add YouTube upload job
        $batchJobs[] = new UploadMatchToYouTube($this->videoUpload);

        // Auto reels if match has finalized stats with qualifying events
        $autoReelJobs = $this->getAutoReelJobs($reelService, $match);
        foreach ($autoReelJobs as $job) {
            $batchJobs[] = $job;
        }

        if ($batchJobs === []) {
            return;
        }

        $videoUpload = $this->videoUpload;

        Bus::batch($batchJobs)
            ->name("video-pipeline-match-{$match->id}")
            ->allowFailures()
            ->then(function () use ($videoUpload) {
                // All jobs succeeded — safe to delete from Bunny
                DeleteBunnyStreamVideo::dispatch($videoUpload);
            })
            ->catch(function (Throwable $e) use ($match) {
                Log::warning("Video pipeline batch had failures for match {$match->id}", [
                    'error' => $e->getMessage(),
                ]);
                // Do NOT delete from Bunny if any job failed
            })
            ->dispatch();
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);
    }

    private function downloadToS3(BunnyStreamService $bunnyService, mixed $match): void
    {
        $tempDir = storage_path('app/temp/pipeline');
        File::ensureDirectoryExists($tempDir);

        $tempFile = $tempDir."/720p-{$match->ulid}.mp4";
        $s3Path = "match-videos/{$match->ulid}.mp4";

        try {
            // Check if already on S3
            if (Storage::disk('s3')->exists($s3Path)) {
                $this->videoUpload->update(['s3_path' => $s3Path]);

                return;
            }

            $bunnyService->downloadVideo($this->videoUpload->bunny_video_id, $tempFile, '720p');

            $stream = fopen($tempFile, 'rb');
            Storage::disk('s3')->put($s3Path, $stream);
            fclose($stream);

            $this->videoUpload->update(['s3_path' => $s3Path]);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /** @return list<GenerateMatchReel> */
    private function getAutoReelJobs(ReelService $reelService, mixed $match): array
    {
        if (! $match->stats_finalized_at) {
            return [];
        }

        // Check if there are qualifying events (goals or highlighted)
        $hasQualifyingEvents = $match->events()
            ->where(function ($q) {
                $q->whereIn('event_type', ['goal', 'penalty_scored'])
                    ->orWhere('highlighted', true);
            })
            ->whereNotNull('player_id')
            ->exists();

        if (! $hasQualifyingEvents) {
            return [];
        }

        // ReelService dispatches its own batch internally
        $reelService->generateReelsForMatch($match);

        return [];
    }
}
