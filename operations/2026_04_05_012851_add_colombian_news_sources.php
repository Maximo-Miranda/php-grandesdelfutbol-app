<?php

use App\Enums\NewsSourceType;
use App\Models\NewsSource;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperation;

return new class extends OneTimeOperation
{
    protected bool $async = false;

    public function process(): void
    {
        $sources = [
            ['name' => 'FutbolRed - Fútbol Colombiano', 'slug' => 'futbolred-co', 'url' => 'https://www.futbolred.com/rss/futbol-colombiano', 'priority' => 9, 'is_active' => true],
            ['name' => 'FutbolRed - Liga BetPlay', 'slug' => 'futbolred-betplay', 'url' => 'https://www.futbolred.com/rss/futbol-colombiano/liga-betplay', 'priority' => 8, 'is_active' => true],
            ['name' => 'FutbolRed - Selección Colombia', 'slug' => 'futbolred-seleccion', 'url' => 'https://www.futbolred.com/rss/seleccion-colombia', 'priority' => 8, 'is_active' => true],
            ['name' => 'FutbolRed - Colombianos Exterior', 'slug' => 'futbolred-exterior', 'url' => 'https://www.futbolred.com/rss/colombianos-en-el-exterior', 'priority' => 7, 'is_active' => false],
            ['name' => 'Win Sports - Fútbol Colombiano', 'slug' => 'winsports-co', 'url' => 'https://www.winsports.co/rss/futbol-colombiano', 'priority' => 9, 'is_active' => true],
            ['name' => 'El Tiempo - Fútbol Colombiano', 'slug' => 'eltiempo-futbol-co', 'url' => 'https://www.eltiempo.com/rss/deportes/futbol-colombiano.xml', 'priority' => 8, 'is_active' => true],
            ['name' => 'El Colombiano - Fútbol Colombiano', 'slug' => 'elcolombiano-futbol', 'url' => 'https://www.elcolombiano.com/rss/deportes/futbol-colombiano.xml', 'priority' => 7, 'is_active' => true],
            ['name' => 'Pulzo Deportes', 'slug' => 'pulzo-deportes', 'url' => 'https://www.pulzo.com/rss/deportes', 'priority' => 6, 'is_active' => false],
        ];

        foreach ($sources as $source) {
            NewsSource::updateOrCreate(
                ['slug' => $source['slug']],
                [
                    'name' => $source['name'],
                    'type' => NewsSourceType::Rss->value,
                    'url' => $source['url'],
                    'language' => 'es',
                    'priority' => $source['priority'],
                    'is_active' => $source['is_active'],
                    'fetch_interval_minutes' => 30,
                ],
            );
        }

        // Deactivate the general El Tiempo deportes feed (replaced by football-specific)
        NewsSource::where('slug', 'eltiempo-deportes')->update(['is_active' => false]);
    }
};
