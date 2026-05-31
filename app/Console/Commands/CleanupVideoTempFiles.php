<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanupVideoTempFiles extends Command
{
    protected $signature = 'videos:cleanup-temp {--hours=6 : Delete temp video files not modified in this many hours}';

    protected $description = 'Elimina archivos temporales de video huérfanos (descargas/cortes que quedaron tras un worker caído).';

    /** @var array<int, string> */
    private const TEMP_DIRS = ['temp/drive', 'temp/youtube', 'temp/reels', 'temp/pipeline'];

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $threshold = now()->subHours($hours)->getTimestamp();

        $deleted = 0;
        $freedBytes = 0;

        foreach (self::TEMP_DIRS as $dir) {
            $path = storage_path("app/{$dir}");

            if (! File::isDirectory($path)) {
                continue;
            }

            foreach (File::files($path) as $file) {
                if ($file->getExtension() !== 'mp4' && $file->getExtension() !== 'mov' && ! str_ends_with($file->getFilename(), '.partial')) {
                    continue;
                }

                if ($file->getMTime() >= $threshold) {
                    continue;
                }

                $freedBytes += $file->getSize();
                File::delete($file->getPathname());
                $this->line("  Eliminado: {$file->getPathname()}");
                $deleted++;
            }
        }

        $this->info($deleted === 0
            ? 'No hay temporales de video para limpiar.'
            : "Listo: {$deleted} temporales eliminados (".round($freedBytes / 1073741824, 2).' GB liberados).');

        return self::SUCCESS;
    }
}
