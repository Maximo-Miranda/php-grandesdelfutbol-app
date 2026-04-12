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
            // Spain — global football coverage
            ['name' => 'Marca - La Liga', 'slug' => 'marca-la-liga', 'url' => 'https://e00-marca.uecdn.es/rss/futbol/primera-division.xml', 'priority' => 10, 'is_active' => true],
            ['name' => 'Marca - Champions League', 'slug' => 'marca-champions', 'url' => 'https://e00-marca.uecdn.es/rss/futbol/champions-league.xml', 'priority' => 10, 'is_active' => true],
            ['name' => 'Marca - Premier League', 'slug' => 'marca-premier', 'url' => 'https://e00-marca.uecdn.es/rss/futbol/premier-league.xml', 'priority' => 9, 'is_active' => true],
            ['name' => 'Marca - Bundesliga', 'slug' => 'marca-bundesliga', 'url' => 'https://e00-marca.uecdn.es/rss/futbol/bundesliga.xml', 'priority' => 7, 'is_active' => false],
            ['name' => 'Marca - Europa League', 'slug' => 'marca-europa-league', 'url' => 'https://e00-marca.uecdn.es/rss/futbol/europa-league.xml', 'priority' => 7, 'is_active' => false],
            ['name' => 'Marca - Real Madrid', 'slug' => 'marca-real-madrid', 'url' => 'https://e00-marca.uecdn.es/rss/futbol/real-madrid.xml', 'priority' => 8, 'is_active' => false],
            ['name' => 'Marca - Barcelona', 'slug' => 'marca-barcelona', 'url' => 'https://e00-marca.uecdn.es/rss/futbol/barcelona.xml', 'priority' => 8, 'is_active' => false],
            ['name' => 'Marca - Selección', 'slug' => 'marca-seleccion', 'url' => 'https://e00-marca.uecdn.es/rss/futbol/seleccion.xml', 'priority' => 7, 'is_active' => false],
            ['name' => 'AS - Fútbol', 'slug' => 'as-futbol', 'url' => 'https://feeds.as.com/mrss-s/pages/as/site/as.com/section/futbol/portada/', 'priority' => 9, 'is_active' => true],
            ['name' => 'AS - La Liga', 'slug' => 'as-la-liga', 'url' => 'https://feeds.as.com/mrss-s/pages/as/site/as.com/section/futbol/subsection/primera/', 'priority' => 8, 'is_active' => false],
            ['name' => 'AS - Champions League', 'slug' => 'as-champions', 'url' => 'https://feeds.as.com/mrss-s/pages/as/site/as.com/section/futbol/subsection/champions/', 'priority' => 8, 'is_active' => false],
            ['name' => 'AS - Internacional', 'slug' => 'as-internacional', 'url' => 'https://feeds.as.com/mrss-s/pages/as/site/as.com/section/futbol/subsection/internacional/', 'priority' => 8, 'is_active' => true],
            ['name' => 'Mundo Deportivo', 'slug' => 'mundo-deportivo', 'url' => 'https://www.mundodeportivo.com/rss/futbol', 'priority' => 6, 'is_active' => false],
            ['name' => 'Sport.es - Fútbol', 'slug' => 'sport-futbol', 'url' => 'https://www.sport.es/es/rss/futbol/rss.xml', 'priority' => 5, 'is_active' => false],
            ['name' => 'Sport.es - Barcelona', 'slug' => 'sport-barcelona', 'url' => 'https://www.sport.es/es/rss/barca/rss.xml', 'priority' => 5, 'is_active' => false],
            ['name' => 'Sport.es - Real Madrid', 'slug' => 'sport-real-madrid', 'url' => 'https://www.sport.es/es/rss/real-madrid/rss.xml', 'priority' => 5, 'is_active' => false],
            ['name' => 'Sport.es - Champions', 'slug' => 'sport-champions', 'url' => 'https://www.sport.es/es/rss/champions-league/rss.xml', 'priority' => 5, 'is_active' => false],

            // Latin America
            ['name' => 'Olé - Últimas Noticias', 'slug' => 'ole-ultimas', 'url' => 'https://www.ole.com.ar/rss/ultimas-noticias/', 'priority' => 8, 'is_active' => true],
            ['name' => 'Olé - Primera División', 'slug' => 'ole-primera', 'url' => 'https://www.ole.com.ar/rss/futbol-primera/', 'priority' => 7, 'is_active' => false],
            ['name' => 'Olé - Internacional', 'slug' => 'ole-internacional', 'url' => 'https://www.ole.com.ar/rss/futbol-internacional/', 'priority' => 7, 'is_active' => true],
            ['name' => 'Olé - Selección Argentina', 'slug' => 'ole-seleccion', 'url' => 'https://www.ole.com.ar/rss/seleccion/', 'priority' => 6, 'is_active' => false],
            ['name' => 'Olé - Mundial', 'slug' => 'ole-mundial', 'url' => 'https://www.ole.com.ar/rss/mundial/', 'priority' => 7, 'is_active' => false],
            ['name' => 'Olé - River Plate', 'slug' => 'ole-river', 'url' => 'https://www.ole.com.ar/rss/river-plate/', 'priority' => 5, 'is_active' => false],
            ['name' => 'Olé - Boca Juniors', 'slug' => 'ole-boca', 'url' => 'https://www.ole.com.ar/rss/boca-juniors/', 'priority' => 5, 'is_active' => false],
            ['name' => 'El Tiempo - Deportes', 'slug' => 'eltiempo-deportes', 'url' => 'https://www.eltiempo.com/rss/deportes.xml', 'priority' => 7, 'is_active' => false],
            ['name' => 'El Tiempo - Fútbol Colombiano', 'slug' => 'eltiempo-futbol-co', 'url' => 'https://www.eltiempo.com/rss/deportes/futbol-colombiano.xml', 'priority' => 8, 'is_active' => true],
            ['name' => 'FutbolRed - Fútbol Colombiano', 'slug' => 'futbolred-co', 'url' => 'https://www.futbolred.com/rss/futbol-colombiano', 'priority' => 9, 'is_active' => true],
            ['name' => 'FutbolRed - Liga BetPlay', 'slug' => 'futbolred-betplay', 'url' => 'https://www.futbolred.com/rss/futbol-colombiano/liga-betplay', 'priority' => 8, 'is_active' => true],
            ['name' => 'FutbolRed - Selección Colombia', 'slug' => 'futbolred-seleccion', 'url' => 'https://www.futbolred.com/rss/seleccion-colombia', 'priority' => 8, 'is_active' => true],
            ['name' => 'FutbolRed - Colombianos Exterior', 'slug' => 'futbolred-exterior', 'url' => 'https://www.futbolred.com/rss/colombianos-en-el-exterior', 'priority' => 7, 'is_active' => false],
            ['name' => 'Win Sports - Fútbol Colombiano', 'slug' => 'winsports-co', 'url' => 'https://www.winsports.co/rss/futbol-colombiano', 'priority' => 9, 'is_active' => true],
            ['name' => 'El Colombiano - Fútbol Colombiano', 'slug' => 'elcolombiano-futbol', 'url' => 'https://www.elcolombiano.com/rss/deportes/futbol-colombiano.xml', 'priority' => 7, 'is_active' => true],
            ['name' => 'Pulzo Deportes', 'slug' => 'pulzo-deportes', 'url' => 'https://www.pulzo.com/rss/deportes', 'priority' => 6, 'is_active' => false],
            ['name' => 'El Gráfico', 'slug' => 'elgrafico', 'url' => 'https://www.elgrafico.com.ar/rss', 'priority' => 5, 'is_active' => false],
            ['name' => 'Marca México - Liga MX', 'slug' => 'marca-liga-mx', 'url' => 'https://www.marca.com/mx/rss/futbol/liga-mx.xml', 'priority' => 7, 'is_active' => true],

            // Video highlights
            ['name' => 'Scorebat Highlights', 'slug' => 'scorebat', 'url' => 'https://www.scorebat.com/video-api/v3/', 'priority' => 6, 'is_active' => false, 'type' => NewsSourceType::ScorebatApi->value],
        ];

        foreach ($sources as $source) {
            NewsSource::updateOrCreate(
                ['slug' => $source['slug']],
                [
                    'name' => $source['name'],
                    'type' => $source['type'] ?? NewsSourceType::Rss->value,
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
