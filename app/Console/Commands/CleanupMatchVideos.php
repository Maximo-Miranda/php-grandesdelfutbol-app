<?php

namespace App\Console\Commands;

use App\Models\MatchVideoUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupMatchVideos extends Command
{
    protected $signature = 'app:cleanup-match-videos {--days= : Override dias de retencion (default: config)}';

    protected $description = 'Elimina la copia del video en S3 tras el periodo de retencion, siempre que exista respaldo en YouTube y en Drive.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('youtube.storage.s3_reels_source_days', 7));

        $uploads = MatchVideoUpload::query()
            ->whereNotNull('youtube_video_id')
            ->whereNotNull('drive_file_id')
            ->where('encoded_at', '<', now()->subDays($days))
            ->where(function ($query) {
                $query->whereNotNull('s3_path')
                    ->orWhereNotNull('original_s3_path')
                    ->orWhereNotNull('s3_reels_path');
            })
            ->get();

        if ($uploads->isEmpty()) {
            $this->info('No hay videos para limpiar.');

            return self::SUCCESS;
        }

        $deleted = 0;

        foreach ($uploads as $upload) {
            $paths = array_values(array_unique(array_filter([
                $upload->s3_path,
                $upload->original_s3_path,
                $upload->s3_reels_path,
            ])));

            Storage::disk('s3')->delete($paths);

            foreach ($paths as $path) {
                $this->line("  Eliminado S3: {$path}");
            }

            $upload->update([
                's3_path' => null,
                'original_s3_path' => null,
                's3_reels_path' => null,
            ]);

            $deleted++;
        }

        $this->info("Listo: {$deleted} limpiados.");

        return self::SUCCESS;
    }
}
