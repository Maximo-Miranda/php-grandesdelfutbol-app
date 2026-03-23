<?php

namespace App\Jobs;

use App\Models\MatchVideoUpload;
use App\Services\BunnyStreamService;
use App\Services\YouTubeService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class UploadMatchToYouTube implements ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 1800;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [120, 300, 600];

    public function __construct(
        public MatchVideoUpload $videoUpload,
    ) {
        $this->onQueue('video-processing');
    }

    public function handle(BunnyStreamService $bunnyService, YouTubeService $youtubeService): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        // Idempotency: skip if already uploaded
        if ($this->videoUpload->youtube_video_id) {
            return;
        }

        // Skip if YouTube is not configured
        if (! $youtubeService->isConfigured()) {
            return;
        }

        $match = $this->videoUpload->match;
        $club = $match?->club;

        if (! $match || ! $club) {
            return;
        }

        $tempDir = storage_path('app/temp/youtube');
        File::ensureDirectoryExists($tempDir);

        $tempFile = $tempDir.'/'.$this->videoUpload->ulid.'.mp4';

        try {
            // Download the highest quality version from Bunny
            $resolution = $bunnyService->downloadHighestQuality(
                $this->videoUpload->bunny_video_id,
                $tempFile,
            );

            // Build YouTube metadata
            $title = "{$match->title} - {$club->name}";
            $description = $this->buildDescription($match, $club);
            $tags = ['futbol', 'grandesdelfutbol', Str::slug($club->name)];

            // Upload to YouTube
            $youtubeVideoId = $youtubeService->uploadVideo($tempFile, $title, $description, $tags);

            // Save results
            $this->videoUpload->update([
                'youtube_video_id' => $youtubeVideoId,
                'youtube_uploaded_at' => now(),
                'best_resolution' => $resolution,
            ]);
        } finally {
            // Always clean up the temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);
    }

    private function buildDescription(mixed $match, mixed $club): string
    {
        $lines = [
            "{$match->title}",
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
