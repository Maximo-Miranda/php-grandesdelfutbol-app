<?php

namespace App\Jobs;

use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\MatchVideoUpload;
use App\Services\YouTubeService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class UploadMatchToYouTube implements ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 3600;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [120, 300, 600];

    public function __construct(
        public MatchVideoUpload $videoUpload,
    ) {
        $this->onQueue('video-processing');
    }

    public function handle(YouTubeService $youtubeService): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $this->videoUpload->refresh();

        if ($this->videoUpload->youtube_video_id) {
            return;
        }

        if (! $youtubeService->isConfigured()) {
            return;
        }

        if ($this->isDailyLimitReached()) {
            return;
        }

        $match = $this->videoUpload->match;
        $club = $match?->club;

        if (! $match || ! $club || ! $this->videoUpload->s3_path) {
            return;
        }

        $lock = Cache::lock("youtube-upload-{$this->videoUpload->id}", 3600);

        if (! $lock->get()) {
            return;
        }

        $tempDir = storage_path('app/temp/youtube');
        File::ensureDirectoryExists($tempDir);

        $tempFile = $tempDir.'/'.$this->videoUpload->ulid.'.mp4';

        // Clean up stale temp file from a previous crashed attempt
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }

        try {
            $s3Path = $this->videoUpload->original_s3_path ?? $this->videoUpload->s3_path;
            $stream = Storage::disk('s3')->readStream($s3Path);

            if (! $stream) {
                throw new \RuntimeException('No se pudo leer el video de S3.');
            }

            $local = fopen($tempFile, 'wb');
            stream_copy_to_stream($stream, $local);
            fclose($local);
            fclose($stream);

            $title = "{$match->title} - {$club->name}";
            $description = $this->buildDescription($match, $club);
            $tags = ['futbol', 'grandesdelfutbol', Str::slug($club->name)];

            $youtubeVideoId = $youtubeService->uploadVideo($tempFile, $title, $description, $tags);

            $this->videoUpload->update([
                'youtube_video_id' => $youtubeVideoId,
                'youtube_uploaded_at' => now(),
            ]);

            $this->incrementDailyCounter();
            $this->addToClubPlaylist($youtubeService, $club, $youtubeVideoId);
        } finally {
            $lock->release();

            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        // Don't retry if YouTube quota exceeded — the scheduled command will handle it
        if ($exception && str_contains($exception->getMessage(), 'uploadLimitExceeded')) {
            $this->delete();
        }

        report($exception);
    }

    private function isDailyLimitReached(): bool
    {
        $limit = config('youtube.daily_upload_limit', 6);
        $count = (int) Cache::get($this->dailyCacheKey(), 0);

        return $count >= $limit;
    }

    private function incrementDailyCounter(): void
    {
        $key = $this->dailyCacheKey();

        if (! Cache::has($key)) {
            Cache::put($key, 0, now()->endOfDay());
        }

        Cache::increment($key);
    }

    private function dailyCacheKey(): string
    {
        return 'youtube-daily-uploads:'.now()->format('Y-m-d');
    }

    private function addToClubPlaylist(YouTubeService $youtubeService, Club $club, string $videoId): void
    {
        try {
            if (! $club->youtube_playlist_id) {
                $playlistId = $youtubeService->createPlaylist(
                    $club->name,
                    "Partidos de {$club->name} - Grandes del Futbol",
                );

                $club->update(['youtube_playlist_id' => $playlistId]);
            }

            $youtubeService->addToPlaylist($club->youtube_playlist_id, $videoId);
        } catch (Throwable $e) {
            // Non-critical: video is uploaded, playlist is optional
            report($e);
        }
    }

    private function buildDescription(FootballMatch $match, Club $club): string
    {
        $lines = [
            $match->title,
            "Club: {$club->name}",
        ];

        if ($match->scheduled_at) {
            $lines[] = 'Fecha: '.$match->scheduled_at->format('d/m/Y');
        }

        if ($match->share_token) {
            $lines[] = '';
            $lines[] = route('match.public', $match->share_token);
        }

        $lines[] = '';
        $lines[] = 'Grandes del Futbol - grandesdelfutbol.com';

        return implode("\n", $lines);
    }
}
