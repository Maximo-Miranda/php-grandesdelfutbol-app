<?php

use App\Enums\VideoUploadStatus;
use App\Jobs\MigrateBunnyVideoToS3;
use App\Models\MatchVideoUpload;
use Illuminate\Support\Facades\Log;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperation;

return new class extends OneTimeOperation
{
    protected bool $async = false;

    protected ?string $tag = 'bunny-migration';

    /**
     * Dispatch a migration job for each Bunny video that hasn't been migrated to S3 yet.
     * Each job runs independently on the video-processing queue.
     */
    public function process(): void
    {
        $videos = MatchVideoUpload::query()
            ->whereNotNull('bunny_video_id')
            ->where('status', VideoUploadStatus::Ready)
            ->whereNull('s3_path')
            ->get();

        if ($videos->isEmpty()) {
            Log::info('MigrateBunnyVideos: No videos to migrate.');

            return;
        }

        Log::info("MigrateBunnyVideos: Dispatching {$videos->count()} migration jobs.");

        foreach ($videos as $videoUpload) {
            MigrateBunnyVideoToS3::dispatch($videoUpload);
        }
    }
};
