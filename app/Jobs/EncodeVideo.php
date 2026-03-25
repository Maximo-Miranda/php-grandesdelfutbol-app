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

class EncodeVideo implements ShouldQueue
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
        $outputFile = $tempDir."/1080p-{$match->ulid}.mp4";
        $s3Output = "videos/matches/{$match->ulid}/1080p.mp4";

        // Clean up stale download from a previous crashed attempt
        $this->cleanupFile($inputFile);

        try {
            $this->downloadFromS3($this->videoUpload->s3_path, $inputFile);

            $properties = $this->getVideoProperties($inputFile);

            if ($properties['duration'] > 0) {
                $this->videoUpload->update(['duration_seconds' => $properties['duration']]);
            }

            if ($this->shouldStreamCopy($properties)) {
                $this->streamCopy($inputFile, $outputFile, $properties);
            } else {
                $this->encode($inputFile, $outputFile, $properties);
            }

            $this->uploadToS3($outputFile, $s3Output);

            $this->videoUpload->update([
                'best_resolution' => '1080p',
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

    private function getVideoProperties(string $filePath): array
    {
        $result = Process::timeout(30)->run([
            'ffprobe', '-v', 'quiet',
            '-show_entries', 'stream=codec_name,codec_type,height',
            '-show_entries', 'format=duration',
            '-of', 'json',
            $filePath,
        ]);

        if (! $result->successful()) {
            return $this->fallbackProperties();
        }

        $data = json_decode($result->output(), true);

        if (! is_array($data)) {
            return $this->fallbackProperties();
        }

        $videoHeight = 0;
        $videoCodec = 'unknown';
        $audioCodec = null;

        foreach ($data['streams'] ?? [] as $stream) {
            if (($stream['codec_type'] ?? '') === 'video' && $videoHeight === 0) {
                $videoHeight = (int) ($stream['height'] ?? 0);
                $videoCodec = $stream['codec_name'] ?? 'unknown';
            }

            if (($stream['codec_type'] ?? '') === 'audio' && $audioCodec === null) {
                $audioCodec = $stream['codec_name'] ?? null;
            }
        }

        $duration = (int) round((float) ($data['format']['duration'] ?? 0));

        return [
            'video_height' => $videoHeight,
            'video_codec' => $videoCodec,
            'audio_codec' => $audioCodec,
            'duration' => $duration,
        ];
    }

    /**
     * @return array{video_height: int, video_codec: string, audio_codec: string|null, duration: int}
     */
    private function fallbackProperties(): array
    {
        return [
            'video_height' => PHP_INT_MAX,
            'video_codec' => 'unknown',
            'audio_codec' => null,
            'duration' => 0,
        ];
    }

    /**
     * @param  array{video_height: int, video_codec: string, audio_codec: string|null, duration: int}  $properties
     */
    private function shouldStreamCopy(array $properties): bool
    {
        return $properties['video_height'] <= 1080
            && $properties['video_height'] > 0
            && $properties['video_codec'] === 'h264';
    }

    /**
     * @param  array{video_height: int, video_codec: string, audio_codec: string|null, duration: int}  $properties
     */
    private function streamCopy(string $inputFile, string $outputFile, array $properties): void
    {
        $command = [
            'ffmpeg', '-y',
            '-i', $inputFile,
            '-c:v', 'copy',
            ...$this->audioArgs($properties),
            '-movflags', '+faststart',
            $outputFile,
        ];

        $result = Process::timeout(300)->run($command);

        if (! $result->successful()) {
            throw new RuntimeException('FFmpeg stream copy failed: '.$result->errorOutput());
        }

        $this->validateOutput($outputFile);
    }

    /**
     * @param  array{video_height: int, video_codec: string, audio_codec: string|null, duration: int}  $properties
     */
    private function encode(string $inputFile, string $outputFile, array $properties): void
    {
        $command = [
            'ffmpeg', '-y',
            '-threads', '0',
            '-i', $inputFile,
            '-c:v', 'libx264',
            '-crf', '23',
            '-preset', 'fast',
            '-vf', 'scale=-2:1080',
            ...$this->audioArgs($properties),
            '-movflags', '+faststart',
            $outputFile,
        ];

        $result = Process::timeout(7200)->run($command);

        if (! $result->successful()) {
            throw new RuntimeException('FFmpeg encoding failed: '.$result->errorOutput());
        }

        $this->validateOutput($outputFile);
    }

    /**
     * @param  array{video_height: int, video_codec: string, audio_codec: string|null, duration: int}  $properties
     * @return list<string>
     */
    private function audioArgs(array $properties): array
    {
        if ($properties['audio_codec'] === 'aac') {
            return ['-c:a', 'copy'];
        }

        return ['-c:a', 'aac', '-b:a', '128k'];
    }

    private function validateOutput(string $outputFile): void
    {
        if (! file_exists($outputFile) || filesize($outputFile) === 0) {
            throw new RuntimeException('FFmpeg did not produce a valid output file.');
        }
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
}
