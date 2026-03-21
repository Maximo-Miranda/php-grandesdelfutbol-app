<?php

namespace App\Console\Commands;

use App\Enums\VideoUploadStatus;
use App\Models\MatchVideoUpload;
use App\Services\BunnyStreamService;
use Illuminate\Console\Command;

class CleanupStalledVideoUploads extends Command
{
    protected $signature = 'video-uploads:cleanup {--hours=48 : Hours after which uploading status is considered stalled}';

    protected $description = 'Elimina subidas de video estancadas en estado "uploading" y las limpia de Bunny Stream.';

    public function handle(BunnyStreamService $bunnyService): int
    {
        $hours = (int) $this->option('hours');

        $stalled = MatchVideoUpload::query()
            ->where('status', VideoUploadStatus::Uploading)
            ->where('created_at', '<', now()->subHours($hours))
            ->get();

        if ($stalled->isEmpty()) {
            $this->info('No hay subidas estancadas para limpiar.');

            return self::SUCCESS;
        }

        foreach ($stalled as $upload) {
            try {
                $bunnyService->deleteVideo($upload->bunny_video_id);
            } catch (\Throwable) {
                // Bunny cleanup is best-effort
            }

            $upload->delete();

            $this->line("  Eliminada: {$upload->original_filename} (match #{$upload->football_match_id})");
        }

        $this->info("Listo: {$stalled->count()} subidas estancadas eliminadas.");

        return self::SUCCESS;
    }
}
