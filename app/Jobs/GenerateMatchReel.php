<?php

namespace App\Jobs;

use App\Enums\ReelStatus;
use App\Enums\VideoUploadStatus;
use App\Models\FootballMatch;
use App\Models\MatchReel;
use App\Models\MatchVideoUpload;
use App\Services\GoogleDriveService;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GenerateMatchReel implements ShouldQueue
{
    use Batchable, Queueable;

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new WithoutOverlapping($this->reel->id)];
    }

    public int $timeout = 1800;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 120, 300];

    public function __construct(
        public MatchReel $reel,
    ) {
        $this->onQueue('reels');
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $match = $this->reel->match;
        $videoUpload = $match?->videoUpload;

        if (! $videoUpload || $videoUpload->status !== VideoUploadStatus::Ready) {
            $this->markFailed('No hay video listo para este partido.');

            return;
        }

        $hasVideoSource = $videoUpload->s3_reels_path
            || $videoUpload->s3_path
            || $videoUpload->drive_reels_file_id
            || $videoUpload->drive_file_id;

        if (! $hasVideoSource) {
            $this->markFailed('No hay video disponible para generar el reel.');

            return;
        }

        $this->reel->update(['status' => ReelStatus::Processing]);

        $tempDir = storage_path('app/temp/reels');
        File::ensureDirectoryExists($tempDir);

        $outputFile = $tempDir.'/'.$this->reel->ulid.'.mp4';
        $sourceVideo = null;

        try {
            $sourceVideo = $this->ensureFullVideoDownloaded($match, $videoUpload, $tempDir);
            $this->cutSegment($sourceVideo, $outputFile);
            $this->storeOutputAndComplete($outputFile);
        } catch (RuntimeException $e) {
            $this->cleanupFiles($outputFile, $sourceVideo, $match);

            if ($this->isRetryableDownloadError($e)) {
                throw $e;
            }

            $this->markFailed($e->getMessage());
        } catch (Exception $e) {
            $this->cleanupFiles($outputFile, $sourceVideo, $match);
            $this->markFailed($e->getMessage());
        }
    }

    /**
     * Download the video source for reel generation.
     *
     * Cascade: S3 720p → S3 legacy → Drive 720p → Drive original
     */
    protected function ensureFullVideoDownloaded(FootballMatch $match, MatchVideoUpload $videoUpload, string $tempDir): string
    {
        $localPath = $tempDir."/full-{$match->ulid}.mp4";

        if (File::exists($localPath)) {
            return $localPath;
        }

        $lock = Cache::lock("reel-download-{$match->ulid}", 1500);
        $lock->block(1500);

        $partialPath = $localPath.'.partial';

        try {
            if (File::exists($localPath)) {
                return $localPath;
            }

            $this->downloadToPartial($videoUpload, $partialPath);
            File::move($partialPath, $localPath);

            return $localPath;
        } finally {
            File::delete($partialPath);
            $lock->release();
        }
    }

    protected function downloadToPartial(MatchVideoUpload $videoUpload, string $partialPath): void
    {
        if ($videoUpload->s3_reels_path && Storage::disk('s3')->exists($videoUpload->s3_reels_path)) {
            $this->downloadFromS3($videoUpload->s3_reels_path, $partialPath);

            return;
        }

        if ($videoUpload->s3_path && Storage::disk('s3')->exists($videoUpload->s3_path)) {
            $this->downloadFromS3($videoUpload->s3_path, $partialPath);

            return;
        }

        if ($videoUpload->drive_reels_file_id) {
            app(GoogleDriveService::class)->downloadFile($videoUpload->drive_reels_file_id, $partialPath);

            return;
        }

        if ($videoUpload->drive_file_id) {
            app(GoogleDriveService::class)->downloadFile($videoUpload->drive_file_id, $partialPath);

            return;
        }

        throw new RuntimeException('No se encontró ninguna fuente de video para generar el reel.');
    }

    protected function downloadFromS3(string $s3Path, string $localPath): void
    {
        $stream = Storage::disk('s3')->readStream($s3Path);
        $local = fopen($localPath, 'wb');
        stream_copy_to_stream($stream, $local);
        fclose($local);
        fclose($stream);
    }

    protected function cutSegment(string $sourceFile, string $outputFile): void
    {
        $start = $this->reel->start_second;
        $duration = $this->reel->end_second - $this->reel->start_second;

        $command = [
            'ffmpeg', '-y',
            '-ss', (string) $start,
            '-i', $sourceFile,
            ...$this->watermarkArgs(),
            '-t', (string) $duration,
            '-c:v', 'libx264', '-preset', 'fast', '-crf', '23',
            '-c:a', 'aac',
            '-movflags', '+faststart',
            $outputFile,
        ];

        $result = Process::timeout(120)->run($command);

        if (! $result->successful()) {
            throw new RuntimeException('ffmpeg cut failed: '.$result->errorOutput());
        }
    }

    /** @return list<string> */
    protected function watermarkArgs(): array
    {
        $path = config('reels.watermark_path');

        if (! config('reels.watermark_enabled') || ! $path || ! File::exists($path)) {
            return [];
        }

        $opacity = config('reels.watermark_opacity', 0.9);
        $padding = config('reels.watermark_padding', 20);

        return [
            '-i', $path,
            '-filter_complex',
            "[1:v]format=rgba,colorchannelmixer=aa={$opacity}[wm];[0:v][wm]overlay=W-w-{$padding}:{$padding}",
        ];
    }

    protected function storeOutputAndComplete(string $outputFile): void
    {
        if (! File::exists($outputFile)) {
            throw new RuntimeException('ffmpeg did not produce an output file.');
        }

        $sizeBytes = File::size($outputFile);
        if ($sizeBytes < 10240) {
            throw new RuntimeException("ffmpeg output file too small ({$sizeBytes} bytes), likely corrupt.");
        }

        $this->reel->addMedia($outputFile)
            ->toMediaCollection('reel');

        $this->reel->update([
            'status' => ReelStatus::Completed,
            'processed_at' => now(),
        ]);
    }

    protected function cleanupFiles(string $outputFile, ?string $sourceVideo, ?FootballMatch $match): void
    {
        if (File::exists($outputFile)) {
            File::delete($outputFile);
        }

        if (! $sourceVideo || ! $match) {
            return;
        }

        $hasPendingReels = $match->reels()
            ->whereIn('status', [ReelStatus::Pending, ReelStatus::Processing])
            ->where('id', '!=', $this->reel->id)
            ->exists();

        if (! $hasPendingReels && File::exists($sourceVideo)) {
            File::delete($sourceVideo);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->markFailed($exception?->getMessage() ?? 'El proceso fue interrumpido. Intenta de nuevo.');
    }

    private function isRetryableDownloadError(RuntimeException $e): bool
    {
        return str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), '403');
    }

    protected function markFailed(string $message): void
    {
        $this->reel->update([
            'status' => ReelStatus::Failed,
            'error_message' => mb_substr($message, 0, 1000),
        ]);
    }
}
