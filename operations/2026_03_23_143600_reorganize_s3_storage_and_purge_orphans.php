<?php

use App\Models\MatchReel;
use App\Models\MatchVideoUpload;
use App\Models\PlayerProfile;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperation;

return new class extends OneTimeOperation
{
    protected bool $async = false;

    protected ?string $tag = 's3-reorganize';

    /**
     * Reorganize S3 storage structure and purge orphan files.
     *
     * 1. Move media library files (reels, player photos) from {uuid}/ to media/{type}/{uuid}/
     * 2. Move match videos from match-videos/ to videos/matches/{ulid}/
     * 3. Delete orphan uploads/ directory files (incomplete uploads)
     * 4. Delete orphan UUID directories not referenced by any media record
     */
    public function process(): void
    {
        $disk = Storage::disk('s3');

        $this->moveMediaLibraryFiles($disk);
        $this->moveLegacyMatchVideos($disk);
        $this->purgeOrphanUploads($disk);
        $this->purgeOrphanUuidDirectories($disk);

        Log::info('ReorganizeS3: Complete.');
    }

    private function moveMediaLibraryFiles(FilesystemAdapter $disk): void
    {
        $mediaItems = Media::all();
        $moved = 0;

        foreach ($mediaItems as $media) {
            $oldPath = $media->uuid.'/';
            $newPrefix = match ($media->model_type) {
                MatchReel::class => 'media/reels/',
                PlayerProfile::class => 'media/players/',
                default => 'media/other/',
            };
            $newPath = $newPrefix.$media->uuid.'/';

            if ($oldPath === $newPath) {
                continue;
            }

            $files = $disk->allFiles($oldPath);

            if (empty($files)) {
                continue;
            }

            foreach ($files as $file) {
                $relativePath = str_replace($oldPath, '', $file);
                $destination = $newPath.$relativePath;

                if (! $disk->exists($destination)) {
                    $disk->copy($file, $destination);
                }

                $disk->delete($file);
            }

            // Clean up empty old directory
            $remaining = $disk->allFiles($oldPath);
            if (empty($remaining)) {
                $disk->deleteDirectory($oldPath);
            }

            $moved++;
        }

        Log::info("ReorganizeS3: Moved {$moved} media items to organized paths.");
    }

    private function moveLegacyMatchVideos(FilesystemAdapter $disk): void
    {
        $files = $disk->files('match-videos');

        if (empty($files)) {
            return;
        }

        $moved = 0;

        foreach ($files as $file) {
            $videoUpload = MatchVideoUpload::where('s3_path', $file)->first();

            if (! $videoUpload?->match) {
                continue;
            }

            $newPath = "videos/matches/{$videoUpload->match->ulid}/720p.mp4";

            if (! $disk->exists($newPath)) {
                $disk->copy($file, $newPath);
            }

            $disk->delete($file);
            $videoUpload->update(['s3_path' => $newPath]);
            $moved++;
        }

        // Clean up empty directory
        if (empty($disk->allFiles('match-videos'))) {
            $disk->deleteDirectory('match-videos');
        }

        Log::info("ReorganizeS3: Moved {$moved} legacy match videos.");
    }

    private function purgeOrphanUploads(FilesystemAdapter $disk): void
    {
        $directories = $disk->directories('uploads');

        if (empty($directories)) {
            return;
        }

        $deleted = 0;

        foreach ($directories as $dir) {
            $files = $disk->allFiles($dir);
            $isReferenced = MatchVideoUpload::whereIn('s3_path', $files)->exists();

            if (! $isReferenced) {
                $disk->deleteDirectory($dir);
                $deleted++;
            }
        }

        if (empty($disk->allDirectories('uploads'))) {
            $disk->deleteDirectory('uploads');
        }

        Log::info("ReorganizeS3: Purged {$deleted} orphan upload directories.");
    }

    private function purgeOrphanUuidDirectories(FilesystemAdapter $disk): void
    {
        // Get all UUID directories at root level (old media library format)
        $directories = $disk->directories('/');

        $validPrefixes = ['media', 'videos', 'uploads', 'match-videos'];
        $mediaUuids = Media::pluck('uuid')->toArray();

        $deleted = 0;

        foreach ($directories as $dir) {
            $dirname = basename($dir);

            // Skip known prefixes
            if (in_array($dirname, $validPrefixes)) {
                continue;
            }

            // Check if it looks like a UUID (36 chars with dashes)
            if (strlen($dirname) !== 36 || substr_count($dirname, '-') !== 4) {
                continue;
            }

            // If UUID is not in media table, it's an orphan
            if (! in_array($dirname, $mediaUuids)) {
                $disk->deleteDirectory($dir);
                $deleted++;
            }
        }

        Log::info("ReorganizeS3: Purged {$deleted} orphan UUID directories.");
    }
};
