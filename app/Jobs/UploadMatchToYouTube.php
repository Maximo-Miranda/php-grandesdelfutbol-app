<?php

namespace App\Jobs;

use App\Enums\VideoUploadStatus;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\MatchVideoUpload;
use App\Services\GoogleDriveService;
use App\Services\YouTubeQuotaService;
use App\Services\YouTubeService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class UploadMatchToYouTube implements ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 3600;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [120, 300, 600];

    public function __construct(
        public MatchVideoUpload $videoUpload,
    ) {
        $this->onQueue('video-processing');
    }

    public function handle(YouTubeService $youtubeService, ?YouTubeQuotaService $quotaService = null): void
    {
        $quotaService ??= app(YouTubeQuotaService::class);

        if ($this->batch()?->cancelled()) {
            return;
        }

        $this->videoUpload->refresh();

        // On retry, the video may already be on YouTube but not yet in the playlist.
        if ($this->videoUpload->youtube_video_id) {
            $this->addVideoToClubPlaylist($youtubeService);

            return;
        }

        if (! $youtubeService->isConfigured()) {
            return;
        }

        if (! $quotaService->isQuotaAvailable()) {
            $this->videoUpload->update([
                'error_message' => 'Límite diario de YouTube alcanzado al momento de procesar. Reintenta mañana.',
            ]);

            return;
        }

        $match = $this->videoUpload->match;
        $club = $match?->club;

        if (! $match || ! $club) {
            return;
        }

        if (! $this->videoUpload->drive_file_id && ! $this->videoUpload->s3_path) {
            return;
        }

        $lock = Cache::lock("youtube-upload-{$this->videoUpload->id}", 3600);

        if (! $lock->get()) {
            return;
        }

        $tempDir = storage_path('app/temp/youtube');
        File::ensureDirectoryExists($tempDir);

        $tempFile = $tempDir.'/'.$this->videoUpload->ulid.'.mp4';

        File::delete($tempFile);
        $startTime = microtime(true);

        try {
            $this->createClubPlaylist($youtubeService, $club);
            $this->downloadVideoToTemp($tempFile);

            $title = "{$match->title} - {$club->name}";
            $description = $this->buildDescription($match, $club);
            $tags = ['futbol', 'grandesdelfutbol', Str::slug($club->name)];

            $youtubeVideoId = $youtubeService->uploadVideo($tempFile, $title, $description, $tags);

            $this->videoUpload->update([
                'youtube_video_id' => $youtubeVideoId,
                'youtube_uploaded_at' => now(),
            ]);

            $quotaService->increment();
            $this->addVideoToClubPlaylist($youtubeService);
            $this->cleanupDriveOriginal();

            Log::info('Video uploaded to YouTube', [
                'match' => $match->ulid,
                'youtube_id' => $youtubeVideoId,
                'file_size_mb' => File::exists($tempFile) ? round(File::size($tempFile) / 1048576) : null,
                'source' => $this->videoUpload->drive_file_id ? 'drive' : 's3',
                'elapsed_seconds' => round(microtime(true) - $startTime, 1),
            ]);
        } finally {
            $lock->release();
            File::delete($tempFile);
        }
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);

        $isQuotaExceeded = $exception && str_contains($exception->getMessage(), 'uploadLimitExceeded');
        $errorMessage = $isQuotaExceeded
            ? 'Se alcanzó el límite diario de YouTube. Usa el botón Reintentar mañana.'
            : 'Error al subir a YouTube: '.mb_substr($exception?->getMessage() ?? 'Unknown', 0, 500);

        $this->videoUpload->refresh();

        $this->videoUpload->update([
            'status' => $this->videoUpload->best_resolution
                ? VideoUploadStatus::Ready
                : VideoUploadStatus::Failed,
            'error_message' => $errorMessage,
        ]);
    }

    private function createClubPlaylist(YouTubeService $youtubeService, Club $club): void
    {
        if ($club->youtube_playlist_id && $youtubeService->playlistExists($club->youtube_playlist_id)) {
            return;
        }

        $club->youtube_playlist_id = $youtubeService->createPlaylist(
            $club->name,
            "Partidos de {$club->name} - Grandes del Futbol",
        );

        $club->save();
    }

    private function addVideoToClubPlaylist(YouTubeService $youtubeService): void
    {
        $match = $this->videoUpload->match;
        $club = $match?->club;

        if (! $club || ! $this->videoUpload->youtube_video_id) {
            return;
        }

        $this->createClubPlaylist($youtubeService, $club);
        $youtubeService->addToPlaylist($club->youtube_playlist_id, $this->videoUpload->youtube_video_id);
    }

    private function downloadVideoToTemp(string $tempFile): void
    {
        if ($this->videoUpload->drive_file_id) {
            app(GoogleDriveService::class)->downloadFile($this->videoUpload->drive_file_id, $tempFile);

            return;
        }

        $s3Path = $this->videoUpload->original_s3_path ?? $this->videoUpload->s3_path;
        $stream = Storage::disk('s3')->readStream($s3Path);

        if (! $stream) {
            throw new RuntimeException('No se pudo leer el video de S3.');
        }

        $local = fopen($tempFile, 'wb');
        stream_copy_to_stream($stream, $local);
        fclose($local);
        fclose($stream);
    }

    private function cleanupDriveOriginal(): void
    {
        if (! $this->videoUpload->drive_file_id) {
            return;
        }

        try {
            app(GoogleDriveService::class)->deleteFile($this->videoUpload->drive_file_id);

            $this->videoUpload->update([
                'drive_file_id' => null,
                'drive_shared_at' => null,
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to cleanup Drive original', [
                'upload' => $this->videoUpload->ulid,
                'drive_file_id' => $this->videoUpload->drive_file_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildDescription(FootballMatch $match, Club $club): string
    {
        $lines = [
            $match->title,
            "Club: {$club->name}",
        ];

        if ($match->scheduled_at) {
            $lines[] = 'Fecha: '.$match->scheduled_at->format('d/m/Y');
        }

        if ($match->share_token) {
            $lines[] = '';
            $lines[] = route('match.public', $match->share_token);
        }

        $lines[] = '';
        $lines[] = 'Grandes del Futbol - grandesdelfutbol.com';

        return implode("\n", $lines);
    }
}
