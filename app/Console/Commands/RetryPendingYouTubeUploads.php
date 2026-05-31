<?php

namespace App\Console\Commands;

use App\Actions\Video\DispatchYouTubeUpload;
use App\Enums\VideoUploadStatus;
use App\Models\MatchVideoUpload;
use App\Services\YouTubeQuotaService;
use Illuminate\Console\Command;

class RetryPendingYouTubeUploads extends Command
{
    protected $signature = 'videos:retry-youtube-pending';

    protected $description = 'Reencola a YouTube los videos listos que aún no se subieron (p. ej. quedaron por límite de cuota), respetando la cuota disponible.';

    public function handle(YouTubeQuotaService $quotaService, DispatchYouTubeUpload $dispatchYouTubeUpload): int
    {
        $available = $quotaService->availableSlots();

        if ($available <= 0) {
            $this->info("Sin cuota de YouTube disponible ({$quotaService->quotaLabel()}).");

            return self::SUCCESS;
        }

        // A video pending YouTube is Ready (already on S3, playable) but has no
        // youtube_video_id yet, with a source still available to upload from.
        $pending = MatchVideoUpload::query()
            ->where('status', VideoUploadStatus::Ready)
            ->whereNull('youtube_video_id')
            ->where(function ($query) {
                $query->whereNotNull('s3_path')
                    ->orWhereNotNull('original_s3_path')
                    ->orWhereNotNull('drive_file_id');
            })
            ->orderBy('id')
            ->limit($available)
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No hay videos pendientes de subir a YouTube.');

            return self::SUCCESS;
        }

        foreach ($pending as $upload) {
            $upload->update([
                'youtube_upload_requested_at' => now(),
                'error_message' => null,
            ]);

            $dispatchYouTubeUpload($upload);
        }

        $this->info("Reencolados {$pending->count()} video(s) a YouTube (cuota {$quotaService->quotaLabel()}).");

        return self::SUCCESS;
    }
}
