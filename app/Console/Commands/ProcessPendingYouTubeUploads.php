<?php

namespace App\Console\Commands;

use App\Enums\VideoUploadStatus;
use App\Jobs\UploadMatchToYouTube;
use App\Models\MatchVideoUpload;
use App\Services\YouTubeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ProcessPendingYouTubeUploads extends Command
{
    protected $signature = 'youtube:process-pending';

    protected $description = 'Upload pending videos to YouTube in FIFO order, respecting the daily quota limit.';

    public function handle(YouTubeService $youtubeService): int
    {
        if (! $youtubeService->isConfigured()) {
            $this->info('YouTube not configured. Skipping.');

            return self::SUCCESS;
        }

        $limit = config('youtube.daily_upload_limit', 6);
        $cacheKey = 'youtube-daily-uploads:'.now()->format('Y-m-d');
        $usedToday = (int) Cache::get($cacheKey, 0);
        $available = $limit - $usedToday;

        if ($available <= 0) {
            $this->info("Daily limit reached ({$usedToday}/{$limit}). Skipping.");

            return self::SUCCESS;
        }

        $pending = MatchVideoUpload::query()
            ->where('status', VideoUploadStatus::Ready)
            ->whereNotNull('best_resolution')
            ->whereNotNull('youtube_upload_requested_at')
            ->whereNull('youtube_video_id')
            ->orderBy('youtube_upload_requested_at')
            ->limit($available)
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No pending YouTube uploads.');

            return self::SUCCESS;
        }

        foreach ($pending as $videoUpload) {
            UploadMatchToYouTube::dispatch($videoUpload);
            $this->info("Dispatched YouTube upload for video #{$videoUpload->id} (match {$videoUpload->match?->ulid})");
        }

        $this->info("Dispatched {$pending->count()} YouTube upload(s). Daily usage: {$usedToday}/{$limit}.");

        return self::SUCCESS;
    }
}
