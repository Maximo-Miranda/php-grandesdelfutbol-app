<?php

namespace App\Console\Commands;

use App\Enums\VideoUploadStatus;
use App\Models\MatchVideoUpload;
use App\Services\GoogleDriveService;
use Illuminate\Console\Command;

class CleanupStaleUploads extends Command
{
    protected $signature = 'app:cleanup-stale-uploads {--days=7 : Dias desde la creacion del upload}';

    protected $description = 'Elimina registros de video upload que quedaron en status "uploading" (sesion expirada, usuario abandono).';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $stale = MatchVideoUpload::query()
            ->where('status', VideoUploadStatus::Uploading)
            ->where('created_at', '<', now()->subDays($days))
            ->get();

        if ($stale->isEmpty()) {
            $this->info('No hay uploads huerfanos para limpiar.');

            return self::SUCCESS;
        }

        $deleted = 0;

        foreach ($stale as $upload) {
            if ($upload->drive_file_id) {
                rescue(fn () => app(GoogleDriveService::class)->deleteFile($upload->drive_file_id));
            }

            $upload->delete();
            $deleted++;

            $this->line("  Eliminado: upload {$upload->ulid} (match #{$upload->football_match_id})");
        }

        $this->info("Listo: {$deleted} uploads huerfanos eliminados.");

        return self::SUCCESS;
    }
}
