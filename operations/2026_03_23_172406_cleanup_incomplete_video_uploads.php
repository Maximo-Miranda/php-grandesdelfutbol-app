<?php

use App\Models\MatchVideoUpload;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperation;

return new class extends OneTimeOperation
{
    protected bool $async = false;

    protected ?string $tag = 'cleanup-videos';

    /**
     * Delete video upload records that were never fully migrated to S3 + YouTube.
     * This frees up those matches to have a new video uploaded from the UI.
     */
    public function process(): void
    {
        $incomplete = MatchVideoUpload::query()
            ->where(function ($q) {
                $q->whereNull('youtube_video_id')
                    ->orWhereNull('s3_path');
            })
            ->with('match')
            ->get();

        if ($incomplete->isEmpty()) {
            Log::info('CleanupIncompleteVideos: No incomplete uploads found.');

            return;
        }

        Log::info("CleanupIncompleteVideos: Found {$incomplete->count()} incomplete uploads.");

        foreach ($incomplete as $videoUpload) {
            $matchTitle = $videoUpload->match?->title ?? 'Unknown';

            // Clean up S3 file if it exists
            if ($videoUpload->s3_path && Storage::disk('s3')->exists($videoUpload->s3_path)) {
                Storage::disk('s3')->delete($videoUpload->s3_path);
                Log::info("CleanupIncompleteVideos: Deleted S3 file {$videoUpload->s3_path}");
            }

            $videoUpload->delete();
            Log::info("CleanupIncompleteVideos: Deleted video {$videoUpload->id} for match '{$matchTitle}'");
        }

        Log::info('CleanupIncompleteVideos: Cleanup complete.');
    }
};
