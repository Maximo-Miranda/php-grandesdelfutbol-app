<?php

namespace App\Console\Commands;

use App\Models\NewsDictionaryEntry;
use Illuminate\Console\Command;

class CleanupNewsDictionary extends Command
{
    protected $signature = 'news:cleanup-dictionary {--days=60 : Eliminar entradas AI con 0 matches después de N días}';

    protected $description = 'Elimina entradas del diccionario creadas por AI que no matchean ningún artículo';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $deleted = NewsDictionaryEntry::query()
            ->where('source', 'ai')
            ->where('matches_count', 0)
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        if ($deleted > 0) {
            NewsDictionaryEntry::clearCache();
        }

        $this->info("Se eliminaron {$deleted} entradas AI sin matches (>{$days} días).");

        return self::SUCCESS;
    }
}
