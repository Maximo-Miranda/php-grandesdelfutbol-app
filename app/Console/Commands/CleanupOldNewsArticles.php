<?php

namespace App\Console\Commands;

use App\Enums\NewsInteractionType;
use App\Models\NewsArticle;
use Illuminate\Console\Command;

class CleanupOldNewsArticles extends Command
{
    protected $signature = 'news:cleanup {--days=7 : Eliminar artículos más viejos que N días}';

    protected $description = 'Elimina artículos de noticias antiguos, preservando los guardados por usuarios';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $cutoff = now()->subDays($days);

        $deleted = NewsArticle::query()
            ->where('published_at', '<', $cutoff)
            ->whereDoesntHave('interactions', fn ($q) => $q->where('type', NewsInteractionType::Bookmark))
            ->delete();

        $this->info("Se eliminaron {$deleted} artículos anteriores a {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
