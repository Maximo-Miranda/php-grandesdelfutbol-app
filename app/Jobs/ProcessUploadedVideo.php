<?php

namespace App\Jobs;

use App\Enums\VideoUploadStatus;
use App\Models\FootballMatch;
use App\Models\MatchVideoUpload;
use App\Notifications\MatchVideoUploadedNotification;
use App\Services\GoogleDriveService;
use App\Services\ReelService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
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

        // Capture serializable values for batch closures
        $videoUploadId = $this->videoUpload->id;
        $matchId = $match->id;
        $matchUlid = $match->ulid;
        $originalS3Path = $this->videoUpload->s3_path;

        $driveFileId = $this->videoUpload->drive_file_id;

        Bus::batch([
            new EncodeVideoTo720p($this->videoUpload),
        ])
            ->name("video-pipeline-match-{$matchId}")
            ->onQueue('video-processing')
            ->allowFailures()
            ->finally(function () use ($videoUploadId, $matchId, $matchUlid, $originalS3Path, $driveFileId) {
                $videoUpload = MatchVideoUpload::find($videoUploadId);
                $match = FootballMatch::find($matchId);

                if (! $videoUpload || ! $match) {
                    return;
                }

                $reelsPath = "videos/matches/{$matchUlid}/720p.mp4";

                if (Storage::disk('s3')->exists($reelsPath)) {
                    $videoUpload->update([
                        's3_path' => $reelsPath,
                    ]);
                }

                if ($originalS3Path && $originalS3Path !== $reelsPath) {
                    rescue(fn () => Storage::disk('s3')->delete($originalS3Path));
                    $videoUpload->update(['original_s3_path' => null]);
                }

                if ($driveFileId && ! $videoUpload->drive_shared_at) {
                    rescue(function () use ($driveFileId, $videoUpload) {
                        app(GoogleDriveService::class)->shareFilePublicly($driveFileId);
                        $videoUpload->update(['drive_shared_at' => now()]);
                    });
                }

                $videoUpload->refresh();

                if ($videoUpload->best_resolution) {
                    $videoUpload->update([
                        'status' => VideoUploadStatus::Ready,
                        'encoded_at' => now(),
                        'error_message' => null,
                    ]);

                    rescue(function () use ($match) {
                        $users = $match->club?->approvedMemberUsers();

                        if ($users?->isNotEmpty()) {
                            Notification::send($users, new MatchVideoUploadedNotification($match));
                        }
                    });
                } else {
                    $videoUpload->update([
                        'status' => VideoUploadStatus::Failed,
                        'error_message' => 'El procesamiento del video falló. Puedes reintentar.',
                    ]);
                }

                rescue(function () use ($match, $videoUpload) {
                    if (! $match->stats_finalized_at || ! $videoUpload->best_resolution) {
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
            })
            ->dispatch();
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

        $tempPath = "{$tempDir}/{$matchUlid}.mp4";

        try {
            $driveService->downloadFile($this->videoUpload->drive_file_id, $tempPath);

            $s3Key = "uploads/{$matchUlid}/original.mp4";
            Storage::disk('s3')->put($s3Key, fopen($tempPath, 'rb'));

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
