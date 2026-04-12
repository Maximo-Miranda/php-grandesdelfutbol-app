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
            [
                'name' => 'La Nación - Fútbol',
                'slug' => 'lanacion-futbol',
                'url' => 'https://www.lanacion.com.ar/arc/outboundfeeds/rss/category/deportes/futbol/',
                'priority' => 10,
                'is_active' => true,
            ],
            [
                'name' => 'La Nación - Deportes',
                'slug' => 'lanacion-deportes',
                'url' => 'https://www.lanacion.com.ar/arc/outboundfeeds/rss/category/deportes/',
                'priority' => 8,
                'is_active' => false,
            ],
            [
                'name' => 'AS - Primera División',
                'slug' => 'as-primera',
                'url' => 'https://as.com/rss/futbol/primera.xml',
                'priority' => 10,
                'is_active' => true,
            ],
            [
                'name' => 'AS - Fútbol Internacional (rich)',
                'slug' => 'as-internacional-rich',
                'url' => 'https://as.com/rss/futbol/internacional.xml',
                'priority' => 10,
                'is_active' => true,
            ],
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
    }
};
