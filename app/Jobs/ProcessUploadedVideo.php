<?php

namespace App\Jobs;

use App\Actions\Video\DispatchYouTubeUpload;
use App\Enums\VideoProcessingStage;
use App\Enums\VideoResolution;
use App\Enums\VideoUploadStatus;
use App\Models\MatchVideoUpload;
use App\Notifications\MatchVideoUploadedNotification;
use App\Services\GoogleDriveService;
use App\Services\ReelService;
use App\Services\S3VideoStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ProcessUploadedVideo implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;

    public int $tries = 2;

    public function __construct(
        public MatchVideoUpload $videoUpload,
    ) {
        $this->onQueue('video-processing');
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new WithoutOverlapping($this->videoUpload->id)];
    }

    public function handle(): void
    {
        $match = $this->videoUpload->match;

        if (! $match) {
            return;
        }

        if ($this->videoUpload->drive_file_id && ! $this->videoUpload->s3_path) {
            Log::info('Transferring video from Drive to S3', ['match' => $match->ulid]);
            $this->transferFromDriveToS3($match->ulid);
        }

        if (! $this->videoUpload->s3_path) {
            return;
        }

        if ($this->videoUpload->drive_file_id && ! $this->videoUpload->drive_shared_at) {
            rescue(function () {
                app(GoogleDriveService::class)->shareFilePublicly($this->videoUpload->drive_file_id);
                $this->videoUpload->update(['drive_shared_at' => now()]);
            });
        }

        $this->videoUpload->update([
            'best_resolution' => VideoResolution::Original,
            'status' => VideoUploadStatus::Ready,
            'encoded_at' => now(),
            's3_reels_uploaded_at' => $this->videoUpload->s3_reels_uploaded_at ?? now(),
            'error_message' => null,
        ]);

        // The original now lives on S3, so the YouTube upload reads from there
        // instead of downloading from Drive a second time.
        if (! $this->videoUpload->youtube_video_id) {
            $this->videoUpload->markProcessingStage(VideoProcessingStage::Publishing);
            app(DispatchYouTubeUpload::class)($this->videoUpload);
        } else {
            $this->videoUpload->update(['processing_stage' => null]);
        }

        rescue(function () use ($match) {
            $users = $match->club?->approvedMemberUsers();

            if ($users?->isNotEmpty()) {
                Notification::send($users, new MatchVideoUploadedNotification($match));
            }
        });

        rescue(function () use ($match) {
            if (! $match->stats_finalized_at) {
                return;
            }

            $hasQualifyingEvents = $match->events()
                ->where(function ($q) {
                    $q->whereIn('event_type', ['goal', 'penalty_scored'])
                        ->orWhere('highlighted', true);
                })
                ->exists();

            if ($hasQualifyingEvents) {
                app(ReelService::class)->generateReelsForMatch($match);
            }
        });
    }

    /**
     * Download a video from Google Drive and upload it to S3.
     *
     * This bridges the Drive upload with the existing S3-based encoding pipeline.
     */
    private function transferFromDriveToS3(string $matchUlid): void
    {
        $driveService = app(GoogleDriveService::class);

        $tempDir = storage_path('app/temp/drive');
        File::ensureDirectoryExists($tempDir);

        $extension = $this->videoUpload->originalExtension();
        $tempPath = "{$tempDir}/{$matchUlid}.{$extension}";

        try {
            $this->videoUpload->markProcessingStage(VideoProcessingStage::Receiving);

            $lastBeat = 0;
            $driveService->downloadFile($this->videoUpload->drive_file_id, $tempPath, function () use (&$lastBeat) {
                $now = time();
                if ($now - $lastBeat >= 10) {
                    $lastBeat = $now;
                    $this->videoUpload->touchProcessingHeartbeat();
                }
            });

            if (! File::exists($tempPath) || File::size($tempPath) === 0) {
                throw new RuntimeException("La descarga de Drive no produjo un archivo válido para el match {$matchUlid}.");
            }

            $this->videoUpload->markProcessingStage(VideoProcessingStage::Storing);

            $s3Key = "uploads/{$matchUlid}/original.{$extension}";
            app(S3VideoStorage::class)->putFile($tempPath, $s3Key);

            $this->videoUpload->update([
                's3_path' => $s3Key,
                'original_s3_path' => $s3Key,
            ]);
        } finally {
            File::delete($tempPath);
        }
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);

        $this->videoUpload->update([
            'status' => VideoUploadStatus::Failed,
            'error_message' => 'Error al iniciar procesamiento: '.mb_substr($exception?->getMessage() ?? 'Unknown', 0, 500),
        ]);
    }
}
