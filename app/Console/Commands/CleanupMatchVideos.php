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

        $deleted = 0;

        MatchVideoUpload::query()
            ->whereNotNull('youtube_video_id')
            ->whereNotNull('drive_file_id')
            ->where('encoded_at', '<', now()->subDays($days))
            ->where(function ($query) {
                $query->whereNotNull('s3_path')
                    ->orWhereNotNull('original_s3_path')
                    ->orWhereNotNull('s3_reels_path');
            })
            ->chunkById(100, function ($uploads) use (&$deleted) {
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
                }

                $uploads->toQuery()->update([
                    's3_path' => null,
                    'original_s3_path' => null,
                    's3_reels_path' => null,
                ]);

                $deleted += $uploads->count();
            });

        $this->info($deleted === 0 ? 'No hay videos para limpiar.' : "Listo: {$deleted} limpiados.");

        return self::SUCCESS;
    }
}
