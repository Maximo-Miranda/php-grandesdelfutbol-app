<?php

namespace App\Jobs;

use App\Models\MatchVideoUpload;
use App\Services\BunnyStreamService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class MigrateBunnyVideoToS3 implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;

    public int $tries = 2;

    /** @var array<int, int> */
    public array $backoff = [300, 600];

    public function __construct(
        public MatchVideoUpload $videoUpload,
    ) {
        $this->onQueue('video-processing');
    }

    public function handle(BunnyStreamService $bunnyService): void
    {
        // Skip if already migrated
        if ($this->videoUpload->s3_path) {
            return;
        }

        $match = $this->videoUpload->match;

        if (! $match || ! $this->videoUpload->bunny_video_id) {
            return;
        }

        $tempDir = storage_path('app/temp/bunny-migration');
        File::ensureDirectoryExists($tempDir);

        $downloadFile = $tempDir."/bunny-{$match->ulid}.mp4";
        $encodedFile = $tempDir."/720p-{$match->ulid}.mp4";
        $s3Path = "videos/matches/{$match->ulid}/720p.mp4";

        try {
            // Download 720p from Bunny
            Log::info("MigrateBunny: Downloading {$this->videoUpload->bunny_video_id} for {$match->title}");
            $bunnyService->downloadVideo($this->videoUpload->bunny_video_id, $downloadFile, '720p');

            // Get duration
            $duration = $this->getVideoDuration($downloadFile);

            // Encode to 720p
            Log::info("MigrateBunny: Encoding 720p for {$match->title}");
            $this->encodeTo720p($downloadFile, $encodedFile);

            // Upload to S3
            Log::info("MigrateBunny: Uploading to S3: {$s3Path}");
            $stream = fopen($encodedFile, 'rb');
            Storage::disk('s3')->put($s3Path, $stream);
            fclose($stream);

            // Update record
            $this->videoUpload->update([
                's3_path' => $s3Path,
                'best_resolution' => '720p',
                'duration_seconds' => $duration > 0 ? $duration : $this->videoUpload->duration_seconds,
            ]);

            // Dispatch YouTube upload
            if (! $this->videoUpload->youtube_video_id) {
                UploadMatchToYouTube::dispatch($this->videoUpload);
                Log::info("MigrateBunny: YouTube upload dispatched for {$match->title}");
            }

            Log::info("MigrateBunny: Successfully migrated {$match->title}");
        } finally {
            if (file_exists($downloadFile)) {
                unlink($downloadFile);
            }
            if (file_exists($encodedFile)) {
                unlink($encodedFile);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error("MigrateBunny: Failed for video {$this->videoUpload->id}", [
            'error' => $exception?->getMessage(),
        ]);
    }

    private function getVideoDuration(string $filePath): int
    {
        $result = Process::timeout(30)->run([
            'ffprobe', '-v', 'quiet',
            '-show_entries', 'format=duration',
            '-of', 'csv=p=0',
            $filePath,
        ]);

        return $result->successful() ? (int) round((float) trim($result->output())) : 0;
    }

    private function encodeTo720p(string $inputFile, string $outputFile): void
    {
        $result = Process::timeout(7200)->run([
            'ffmpeg', '-y',
            '-i', $inputFile,
            '-c:v', 'libx264',
            '-crf', '23',
            '-preset', 'fast',
            '-vf', 'scale=-2:720',
            '-c:a', 'aac',
            '-b:a', '128k',
            '-movflags', '+faststart',
            $outputFile,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException('FFmpeg encoding failed: '.$result->errorOutput());
        }
    }
}
