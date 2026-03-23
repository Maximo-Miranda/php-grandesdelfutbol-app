<?php

namespace App\Jobs;

use App\Enums\VideoUploadStatus;
use App\Models\FootballMatch;
use App\Models\MatchVideoUpload;
use App\Services\ReelService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
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
            new EncodeVideoTo720p($this->videoUpload),
            new UploadMatchToYouTube($this->videoUpload),
        ])
            ->name("video-pipeline-match-{$matchId}")
            ->onQueue('video-processing')
            ->allowFailures()
            ->then(function () use ($videoUploadId, $matchId, $matchUlid, $originalS3Path) {
                $videoUpload = MatchVideoUpload::find($videoUploadId);
                $match = FootballMatch::find($matchId);

                if (! $videoUpload || ! $match) {
                    return;
                }

                // Replace original with encoded 720p
                $encodedPath = "videos/matches/{$matchUlid}/720p.mp4";

                if ($originalS3Path !== $encodedPath && Storage::disk('s3')->exists($encodedPath)) {
                    Storage::disk('s3')->delete($originalS3Path);
                    $videoUpload->update(['s3_path' => $encodedPath]);
                }

                // Auto reels if match has finalized stats
                if ($match->stats_finalized_at) {
                    $hasQualifyingEvents = $match->events()
                        ->where(function ($q) {
                            $q->whereIn('event_type', ['goal', 'penalty_scored'])
                                ->orWhere('highlighted', true);
                        })
                        ->whereNotNull('player_id')
                        ->exists();

                    if ($hasQualifyingEvents) {
                        app(ReelService::class)->generateReelsForMatch($match);
                    }
                }

                // Wait for YouTube processing or mark ready
                $videoUpload->refresh();

                if ($videoUpload->youtube_video_id) {
                    WaitForYouTubeProcessing::dispatch($videoUpload);
                } else {
                    $videoUpload->update([
                        'status' => VideoUploadStatus::Ready,
                        'encoded_at' => now(),
                        'error_message' => null,
                    ]);
                }
            })
            ->catch(function (Throwable $e) use ($videoUploadId, $matchId) {
                Log::warning("Video pipeline had failures for match {$matchId}", [
                    'error' => $e->getMessage(),
                ]);

                MatchVideoUpload::where('id', $videoUploadId)->update([
                    'status' => VideoUploadStatus::Failed->value,
                    'error_message' => 'Error al procesar video: '.$e->getMessage(),
                ]);
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
