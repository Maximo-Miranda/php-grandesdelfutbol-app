<?php

namespace App\Console\Commands;

use App\Models\MatchVideoUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupMatchVideos extends Command
{
    protected $signature = 'app:cleanup-match-videos {--days= : Override dias de retencion (default: config)}';

    protected $description = 'Elimina la version 720p de S3 despues del periodo de retencion. Verifica que Drive tenga el respaldo.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('youtube.storage.s3_reels_source_days', 30));

        $uploads = MatchVideoUpload::query()
            ->whereNotNull('s3_reels_path')
            ->where('s3_reels_uploaded_at', '<', now()->subDays($days))
            ->get();

        if ($uploads->isEmpty()) {
            $this->info('No hay videos para limpiar.');

            return self::SUCCESS;
        }

        $deleted = 0;
        $skipped = 0;

        foreach ($uploads as $upload) {
            // Verify Drive has the 720p before deleting S3
            if (! $upload->drive_reels_file_id) {
                $this->warn("  Saltado: {$upload->ulid} — no tiene 720p en Drive.");
                $skipped++;

                continue;
            }

            if (Storage::disk('s3')->exists($upload->s3_reels_path)) {
                Storage::disk('s3')->delete($upload->s3_reels_path);
                $this->line("  Eliminado S3: {$upload->s3_reels_path}");
            }

            // Also clean up any original temp files
            if ($upload->original_s3_path && Storage::disk('s3')->exists($upload->original_s3_path)) {
                Storage::disk('s3')->delete($upload->original_s3_path);
            }

            $upload->update([
                's3_reels_path' => null,
                's3_path' => null,
                'original_s3_path' => null,
            ]);

            $deleted++;
        }

        $this->info("Listo: {$deleted} limpiados, {$skipped} saltados.");

        return self::SUCCESS;
    }
}
