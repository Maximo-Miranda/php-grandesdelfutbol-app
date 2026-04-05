<?php

namespace App\Console\Commands;

use App\Jobs\FetchNewsFromSource;
use App\Models\NewsSource;
use Illuminate\Console\Command;

class FetchNewsArticles extends Command
{
    protected $signature = 'news:fetch';

    protected $description = 'Obtiene artículos de noticias desde todas las fuentes activas';

    public function handle(): int
    {
        $sources = NewsSource::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (NewsSource $source) => $source->needsFetching());

        if ($sources->isEmpty()) {
            $this->info('No hay fuentes que necesiten actualización.');

            return self::SUCCESS;
        }

        foreach ($sources as $source) {
            FetchNewsFromSource::dispatch($source)->onQueue('news-fetching');
            $this->line("Despachado: {$source->name}");
        }

        $this->info("Se despacharon {$sources->count()} trabajos de obtención de noticias.");

        return self::SUCCESS;
    }
}
