<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupMatchVideos extends Command
{
    protected $signature = 'app:cleanup-match-videos {--days=30 : Días desde que terminó el partido}';

    protected $description = 'Elimina videos completos de S3 para partidos antiguos. Los clips generados se mantienen.';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $matches = FootballMatch::query()
            ->whereNotNull('video_path')
            ->where('ended_at', '<', now()->subDays($days))
            ->get();

        if ($matches->isEmpty()) {
            $this->info('No hay videos para limpiar.');

            return self::SUCCESS;
        }

        $disk = Storage::disk('s3');
        $deleted = 0;
        $freedBytes = 0;

        foreach ($matches as $match) {
            if (! $disk->exists($match->video_path)) {
                $match->update(['video_path' => null]);
                $deleted++;

                continue;
            }

            $freedBytes += $disk->size($match->video_path);
            $disk->delete($match->video_path);
            $match->update(['video_path' => null]);
            $deleted++;

            $this->line("  Eliminado: {$match->video_path} ({$match->title})");
        }

        $freedMB = round($freedBytes / 1024 / 1024);
        $this->info("Listo: {$deleted} videos eliminados, ~{$freedMB} MB liberados.");

        return self::SUCCESS;
    }
}
