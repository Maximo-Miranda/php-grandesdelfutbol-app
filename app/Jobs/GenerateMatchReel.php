<?php

namespace App\Jobs;

use App\Enums\ReelStatus;
use App\Enums\VideoUploadStatus;
use App\Models\FootballMatch;
use App\Models\MatchReel;
use App\Services\BunnyStreamService;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GenerateMatchReel implements ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 900;

    public int $tries = 2;

    /** @var array<int, int> */
    public array $backoff = [60, 120];

    public function __construct(
        public MatchReel $reel,
    ) {
        $this->onQueue('reels');
    }

    public function handle(BunnyStreamService $bunnyStreamService): void
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

        $this->reel->update(['status' => ReelStatus::Processing]);

        $tempDir = storage_path('app/temp/reels');
        File::ensureDirectoryExists($tempDir);

        $outputFile = $tempDir.'/'.$this->reel->ulid.'.mp4';
        $sourceVideo = null;

        try {
            $sourceVideo = $this->ensureFullVideoDownloaded($bunnyStreamService, $match, $videoUpload->bunny_video_id, $tempDir);
            $this->cutSegment($sourceVideo, $outputFile);
            $this->storeOutputAndComplete($outputFile);
        } catch (Exception $e) {
            $this->markFailed($e->getMessage());
        } finally {
            $this->cleanupFiles($outputFile, $sourceVideo, $match);
        }
    }

    protected function ensureFullVideoDownloaded(BunnyStreamService $bunnyStreamService, FootballMatch $match, string $bunnyVideoId, string $tempDir): string
    {
        $localPath = $tempDir."/full-{$match->ulid}.mp4";

        if (file_exists($localPath)) {
            return $localPath;
        }

        $s3Path = "match-videos/{$match->ulid}.mp4";

        if (Storage::disk('s3')->exists($s3Path)) {
            $this->downloadFromS3($s3Path, $localPath);

            return $localPath;
        }

        $bunnyStreamService->downloadVideo($bunnyVideoId, $localPath);

        $this->uploadToS3($s3Path, $localPath);

        return $localPath;
    }

    protected function downloadFromS3(string $s3Path, string $localPath): void
    {
        $stream = Storage::disk('s3')->readStream($s3Path);
        $local = fopen($localPath, 'wb');
        stream_copy_to_stream($stream, $local);
        fclose($local);
        fclose($stream);
    }

    protected function uploadToS3(string $s3Path, string $localPath): void
    {
        $stream = fopen($localPath, 'rb');
        Storage::disk('s3')->put($s3Path, $stream);
        fclose($stream);
    }

    protected function cutSegment(string $sourceFile, string $outputFile): void
    {
        $start = $this->reel->start_second;
        $duration = $this->reel->end_second - $this->reel->start_second;

        $result = Process::timeout(120)->run([
            'ffmpeg',
            '-y',
            '-i', $sourceFile,
            '-ss', (string) $start,
            '-t', (string) $duration,
            '-c:v', 'libx264',
            '-preset', 'fast',
            '-crf', '23',
            '-c:a', 'aac',
            '-movflags', '+faststart',
            $outputFile,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException('ffmpeg cut failed: '.$result->errorOutput());
        }
    }

    protected function storeOutputAndComplete(string $outputFile): void
    {
        if (! file_exists($outputFile)) {
            throw new RuntimeException('ffmpeg did not produce an output file.');
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
        if (file_exists($outputFile)) {
            unlink($outputFile);
        }

        if ($sourceVideo && $match) {
            $this->cleanupLocalVideoIfDone($match, $sourceVideo);
        }
    }

    protected function cleanupLocalVideoIfDone(FootballMatch $match, string $localPath): void
    {
        $hasPendingReels = $match->reels()
            ->whereIn('status', [ReelStatus::Pending, ReelStatus::Processing])
            ->where('id', '!=', $this->reel->id)
            ->exists();

        if (! $hasPendingReels && file_exists($localPath)) {
            unlink($localPath);
        }
    }

    /** Called by Laravel when the job exhausts all retries or is killed by timeout. */
    public function failed(?Throwable $exception): void
    {
        $this->markFailed($exception?->getMessage() ?? 'El proceso fue interrumpido. Intenta de nuevo.');
    }

    protected function markFailed(string $message): void
    {
        $this->reel->update([
            'status' => ReelStatus::Failed,
            'error_message' => mb_substr($message, 0, 1000),
        ]);
    }
}
