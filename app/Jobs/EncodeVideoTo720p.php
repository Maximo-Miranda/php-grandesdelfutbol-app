<?php

namespace App\Jobs;

use App\Models\MatchVideoUpload;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class EncodeVideoTo720p implements ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 7200;

    public int $tries = 2;

    /** @var array<int, int> */
    public array $backoff = [300, 600];

    public function __construct(
        public MatchVideoUpload $videoUpload,
    ) {
        $this->onQueue('video-processing');
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $match = $this->videoUpload->match;

        if (! $match || ! $this->videoUpload->s3_path) {
            return;
        }

        $tempDir = storage_path('app/temp/encoding');
        File::ensureDirectoryExists($tempDir);

        $inputFile = $tempDir."/original-{$match->ulid}.mp4";
        $outputFile = $tempDir."/720p-{$match->ulid}.mp4";
        $s3Output = "videos/matches/{$match->ulid}/720p.mp4";

        try {
            $this->downloadFromS3($this->videoUpload->s3_path, $inputFile);

            $duration = $this->getVideoDuration($inputFile);

            if ($duration > 0) {
                $this->videoUpload->update(['duration_seconds' => $duration]);
            }

            $this->encode($inputFile, $outputFile);
            $this->uploadToS3($outputFile, $s3Output);

            $this->videoUpload->update([
                'best_resolution' => '720p',
            ]);
        } finally {
            $this->cleanupFile($inputFile);
            $this->cleanupFile($outputFile);
        }
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);

        $this->videoUpload->update([
            'error_message' => 'Error al encodear video: '.mb_substr($exception?->getMessage() ?? 'Unknown', 0, 500),
        ]);
    }

    private function cleanupFile(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }

    private function downloadFromS3(string $s3Path, string $localPath): void
    {
        $stream = Storage::disk('s3')->readStream($s3Path);

        if (! $stream) {
            throw new RuntimeException("No se pudo leer el archivo de S3: {$s3Path}");
        }

        $local = fopen($localPath, 'wb');
        stream_copy_to_stream($stream, $local);
        fclose($local);
        fclose($stream);
    }

    private function uploadToS3(string $localPath, string $s3Path): void
    {
        $stream = fopen($localPath, 'rb');
        Storage::disk('s3')->put($s3Path, $stream);
        fclose($stream);
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

    private function encode(string $inputFile, string $outputFile): void
    {
        $command = [
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
        ];

        $result = Process::timeout(7200)->run($command);

        if (! $result->successful()) {
            throw new RuntimeException('FFmpeg encoding failed: '.$result->errorOutput());
        }

        if (! file_exists($outputFile) || filesize($outputFile) === 0) {
            throw new RuntimeException('FFmpeg did not produce a valid output file.');
        }
    }
}
