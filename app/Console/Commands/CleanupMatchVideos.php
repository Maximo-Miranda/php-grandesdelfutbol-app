<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupMatchVideos extends Command
{
    protected $signature = 'app:cleanup-match-videos {--days=30 : Dias desde que se almaceno el video}';

    protected $description = 'Elimina videos completos cacheados en S3 despues de N dias. Los reels generados se mantienen.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $disk = Storage::disk('s3');
        $files = $disk->files('match-videos');

        if (empty($files)) {
            $this->info('No hay videos cacheados para limpiar.');

            return self::SUCCESS;
        }

        $deleted = 0;
        $freedBytes = 0;
        $cutoff = now()->subDays($days)->timestamp;

        foreach ($files as $file) {
            $lastModified = $disk->lastModified($file);

            if ($lastModified < $cutoff) {
                $freedBytes += $disk->size($file);
                $disk->delete($file);
                $deleted++;

                $this->line("  Eliminado: {$file}");
            }
        }

        $freedMB = round($freedBytes / 1024 / 1024);
        $this->info("Listo: {$deleted} videos eliminados, ~{$freedMB} MB liberados.");

        return self::SUCCESS;
    }
}
