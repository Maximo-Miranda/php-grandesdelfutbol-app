<?php

namespace App\Jobs;

use App\Enums\ReelStatus;
use App\Models\MatchReel;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class GenerateMatchReel implements ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 300;

    public int $tries = 2;

    /** @var int[] */
    public array $backoff = [60, 120];

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

        if (! $match?->youtube_url) {
            $this->markFailed('No YouTube URL for match.');

            return;
        }

        $this->reel->update(['status' => ReelStatus::Processing]);

        $tempDir = storage_path('app/temp/reels');
        File::ensureDirectoryExists($tempDir);

        $outputFile = $tempDir.'/'.$this->reel->ulid.'.mp4';

        try {
            $this->downloadSegment($match->youtube_url, $outputFile);

            if (! file_exists($outputFile)) {
                $this->markFailed('yt-dlp did not produce an output file.');

                return;
            }

            $this->reel->addMedia($outputFile)
                ->toMediaCollection('reel');

            $this->reel->update([
                'status' => ReelStatus::Completed,
                'processed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->markFailed($e->getMessage());

            if (file_exists($outputFile)) {
                @unlink($outputFile);
            }
        }
    }

    protected function downloadSegment(string $youtubeUrl, string $outputFile): void
    {
        $start = $this->reel->start_second;
        $end = $this->reel->end_second;

        $result = Process::timeout(240)->run([
            'yt-dlp',
            '--download-sections', "*{$start}-{$end}",
            '-f', 'bestvideo[height<=720]+bestaudio',
            '--merge-output-format', 'mp4',
            '--rate-limit', '5M',
            '--no-cookies',
            '-o', $outputFile,
            $youtubeUrl,
        ]);

        if (! $result->successful()) {
            throw new \RuntimeException('yt-dlp failed: '.$result->errorOutput());
        }
    }

    protected function markFailed(string $message): void
    {
        $this->reel->update([
            'status' => ReelStatus::Failed,
            'error_message' => mb_substr($message, 0, 1000),
        ]);
    }
}
