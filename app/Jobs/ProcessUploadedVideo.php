<?php

namespace App\Jobs;

use App\Enums\VideoUploadStatus;
use App\Models\FootballMatch;
use App\Models\MatchVideoUpload;
use App\Services\ReelService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
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

        if (! $match || ! $this->videoUpload->s3_path) {
            return;
        }

        // Capture serializable values for batch closures
        $videoUploadId = $this->videoUpload->id;
        $matchId = $match->id;
        $matchUlid = $match->ulid;
        $originalS3Path = $this->videoUpload->s3_path;

        Bus::batch([
            new EncodeVideo($this->videoUpload),
        ])
            ->name("video-pipeline-match-{$matchId}")
            ->onQueue('video-processing')
            ->allowFailures()
            ->finally(function () use ($videoUploadId, $matchId, $matchUlid, $originalS3Path) {
                $videoUpload = MatchVideoUpload::find($videoUploadId);
                $match = FootballMatch::find($matchId);

                if (! $videoUpload || ! $match) {
                    return;
                }

                // Point s3_path to the encoded file for playback; keep original for YouTube
                $encodedPath = "videos/matches/{$matchUlid}/1080p.mp4";

                if ($originalS3Path !== $encodedPath && Storage::disk('s3')->exists($encodedPath)) {
                    $videoUpload->update([
                        's3_path' => $encodedPath,
                        'original_s3_path' => $originalS3Path,
                    ]);
                }

                // Determine final status — must always run
                $videoUpload->refresh();

                if ($videoUpload->best_resolution) {
                    $videoUpload->update([
                        'status' => VideoUploadStatus::Ready,
                        'encoded_at' => now(),
                        'error_message' => null,
                    ]);
                } else {
                    $videoUpload->update([
                        'status' => VideoUploadStatus::Failed,
                        'error_message' => 'El procesamiento del video falló. Puedes reintentar.',
                    ]);
                }

                // Auto reels if match has finalized stats (non-critical)
                try {
                    if ($match->stats_finalized_at && $videoUpload->best_resolution) {
                        $hasQualifyingEvents = $match->events()
                            ->where(function ($q) {
                                $q->whereIn('event_type', ['goal', 'penalty_scored'])
                                    ->orWhere('highlighted', true);
                            })
                            ->exists();

                        if ($hasQualifyingEvents) {
                            app(ReelService::class)->generateReelsForMatch($match);
                        }
                    }
                } catch (Throwable $e) {
                    report($e);
                }
            })
            ->dispatch();
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
