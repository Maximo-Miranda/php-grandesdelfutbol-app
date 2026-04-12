<?php

use App\Enums\NewsDictionaryType;
use App\Models\NewsDictionaryEntry;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperation;

return new class extends OneTimeOperation
{
    protected bool $async = false;

    public function process(): void
    {
        $entries = [
            // Competitions
            ['competition', 'la_liga', 'La Liga', ['la liga', 'laliga', 'liga española', 'liga santander', 'liga ea sports', 'primera división', 'primera division']],
            ['competition', 'premier_league', 'Premier League', ['premier league', 'premier', 'epl', 'liga inglesa']],
            ['competition', 'champions_league', 'Champions League', ['champions league', 'champions', 'uefa champions', 'orejona']],
            ['competition', 'europa_league', 'Europa League', ['europa league', 'uefa europa']],
            ['competition', 'serie_a', 'Serie A', ['serie a', 'calcio', 'liga italiana']],
            ['competition', 'bundesliga', 'Bundesliga', ['bundesliga', 'liga alemana']],
            ['competition', 'ligue_1', 'Ligue 1', ['ligue 1', 'ligue1', 'liga francesa']],
            ['competition', 'liga_betplay', 'Liga BetPlay', ['liga betplay', 'betplay', 'liga colombiana', 'fpc']],
            ['competition', 'copa_libertadores', 'Copa Libertadores', ['copa libertadores', 'libertadores']],
            ['competition', 'copa_sudamericana', 'Copa Sudamericana', ['copa sudamericana', 'sudamericana']],
            ['competition', 'liga_mx', 'Liga MX', ['liga mx', 'liga mexicana', 'ligamx']],
            ['competition', 'liga_argentina', 'Liga Argentina', ['liga argentina', 'liga profesional', 'primera división argentina', 'torneo apertura', 'torneo clausura']],
            ['competition', 'copa_america', 'Copa América', ['copa américa', 'copa america']],
            ['competition', 'mundial', 'Mundial', ['mundial', 'world cup', 'copa del mundo', 'mundial 2026']],
            ['competition', 'eliminatorias', 'Eliminatorias', ['eliminatorias', 'clasificatorias', 'qualifiers']],
            ['competition', 'copa_del_rey', 'Copa del Rey', ['copa del rey']],
            ['competition', 'supercopa', 'Supercopa', ['supercopa', 'supercopa de españa']],
            ['competition', 'conference_league', 'Conference League', ['conference league', 'uefa conference']],
            ['competition', 'segunda_division', 'Segunda División', ['segunda división', 'segunda division', 'laliga hypermotion']],

            // Teams — Spain
            ['team', 'real_madrid', 'Real Madrid', ['real madrid', 'madrid', 'merengues', 'blancos', 'rmadrid']],
            ['team', 'barcelona', 'FC Barcelona', ['barcelona', 'barça', 'barca', 'blaugrana', 'culé', 'cule']],
            ['team', 'atletico_madrid', 'Atlético de Madrid', ['atlético de madrid', 'atletico de madrid', 'atletico madrid', 'atlético madrid', 'atleti', 'colchoneros']],
            ['team', 'athletic_bilbao', 'Athletic Bilbao', ['athletic bilbao', 'athletic club', 'athletic', 'leones']],
            ['team', 'real_sociedad', 'Real Sociedad', ['real sociedad', 'la real', 'txuri-urdin']],
            ['team', 'villarreal', 'Villarreal', ['villarreal', 'submarino amarillo']],
            ['team', 'betis', 'Real Betis', ['real betis', 'betis', 'béticos']],
            ['team', 'sevilla', 'Sevilla FC', ['sevilla fc', 'sevilla']],
            ['team', 'valencia', 'Valencia CF', ['valencia cf', 'valencia']],

            // Teams — England
            ['team', 'manchester_city', 'Manchester City', ['manchester city', 'man city', 'city', 'citizens']],
            ['team', 'manchester_united', 'Manchester United', ['manchester united', 'man united', 'united', 'red devils']],
            ['team', 'liverpool', 'Liverpool', ['liverpool', 'reds', 'lfc']],
            ['team', 'arsenal', 'Arsenal', ['arsenal', 'gunners']],
            ['team', 'chelsea', 'Chelsea', ['chelsea', 'blues']],
            ['team', 'tottenham', 'Tottenham', ['tottenham', 'spurs', 'tottenham hotspur']],

            // Teams — Italy
            ['team', 'juventus', 'Juventus', ['juventus', 'juve', 'vecchia signora']],
            ['team', 'ac_milan', 'AC Milan', ['ac milan', 'milan', 'rossoneri']],
            ['team', 'inter_milan', 'Inter de Milán', ['inter de milán', 'inter milan', 'inter', 'internazionale', 'nerazzurri']],
            ['team', 'napoli', 'Napoli', ['napoli', 'napoles']],
            ['team', 'roma', 'AS Roma', ['roma', 'as roma', 'giallorossi']],

            // Teams — Germany & France
            ['team', 'bayern_munich', 'Bayern Múnich', ['bayern múnich', 'bayern munich', 'bayern', 'baviera']],
            ['team', 'borussia_dortmund', 'Borussia Dortmund', ['borussia dortmund', 'dortmund', 'bvb']],
            ['team', 'psg', 'PSG', ['paris saint-germain', 'paris saint germain', 'psg', 'paris']],

            // Teams — Argentina
            ['team', 'boca_juniors', 'Boca Juniors', ['boca juniors', 'boca', 'xeneizes']],
            ['team', 'river_plate', 'River Plate', ['river plate', 'river', 'millonarios', 'millonario']],
            ['team', 'racing', 'Racing Club', ['racing club', 'racing', 'academia']],
            ['team', 'independiente', 'Independiente', ['independiente', 'rojo', 'diablo rojo']],
            ['team', 'san_lorenzo', 'San Lorenzo', ['san lorenzo', 'ciclón']],

            // Teams — Colombia
            ['team', 'atletico_nacional', 'Atlético Nacional', ['atlético nacional', 'atletico nacional', 'nacional', 'verdolaga']],
            ['team', 'millonarios_fc', 'Millonarios FC', ['millonarios fc', 'millonarios', 'embajador']],
            ['team', 'america_cali', 'América de Cali', ['américa de cali', 'america de cali', 'américa']],
            ['team', 'deportivo_cali', 'Deportivo Cali', ['deportivo cali', 'cali', 'azucareros']],
            ['team', 'junior_barranquilla', 'Junior de Barranquilla', ['junior de barranquilla', 'junior', 'tiburón']],
            ['team', 'santa_fe', 'Santa Fe', ['independiente santa fe', 'santa fe', 'cardenal']],

            // Teams — Mexico
            ['team', 'club_america', 'Club América', ['club américa', 'club america', 'águilas', 'aguilas']],
            ['team', 'chivas', 'Chivas', ['chivas', 'guadalajara', 'chivas rayadas', 'rebaño']],
            ['team', 'cruz_azul', 'Cruz Azul', ['cruz azul', 'máquina', 'maquina']],
            ['team', 'pumas', 'Pumas UNAM', ['pumas unam', 'pumas']],
            ['team', 'monterrey', 'Monterrey', ['monterrey', 'rayados']],

            // Teams — Brazil
            ['team', 'flamengo', 'Flamengo', ['flamengo', 'mengão', 'mengao']],
            ['team', 'palmeiras', 'Palmeiras', ['palmeiras', 'verdão', 'verdao']],
            ['team', 'corinthians', 'Corinthians', ['corinthians', 'timão', 'timao']],

            // Teams — Selections
            ['team', 'seleccion_argentina', 'Selección Argentina', ['selección argentina', 'seleccion argentina', 'albiceleste', 'argentina']],
            ['team', 'seleccion_colombia', 'Selección Colombia', ['selección colombia', 'seleccion colombia', 'tricolor', 'colombia']],
            ['team', 'seleccion_mexico', 'Selección México', ['selección mexicana', 'seleccion mexicana', 'tri', 'méxico', 'mexico']],
            ['team', 'seleccion_brasil', 'Selección Brasil', ['selección brasileña', 'seleccion brasileña', 'canarinha', 'brasil', 'brazil']],
            ['team', 'seleccion_espana', 'Selección España', ['selección española', 'seleccion española', 'la roja', 'españa']],

            // Topics
            ['topic', 'transfers', 'Fichajes', ['fichaje', 'fichajes', 'transfer', 'traspaso', 'cesión', 'cesion', 'contratación', 'refuerzo', 'refuerzos', 'mercado de pases']],
            ['topic', 'injuries', 'Lesiones', ['lesión', 'lesion', 'lesionado', 'baja', 'recuperación', 'recuperacion', 'parte médico']],
            ['topic', 'results', 'Resultados', ['resultado', 'gol', 'goles', 'marcador', 'victoria', 'derrota', 'empate']],
            ['topic', 'tactics', 'Táctica', ['táctica', 'tactica', 'formación', 'formacion', 'alineación', 'alineacion', 'esquema']],
            ['topic', 'coaches', 'Entrenadores', ['entrenador', 'director técnico', 'dt', 'técnico', 'banquillo', 'destitución', 'destitucion']],
            ['topic', 'referees', 'Árbitros', ['árbitro', 'arbitro', 'var', 'penalti', 'penal', 'expulsión', 'expulsion', 'tarjeta roja']],
            ['topic', 'youth', 'Cantera', ['cantera', 'juvenil', 'sub-20', 'sub-17', 'promesa', 'joven']],

            // Breaking keywords
            ['breaking_keyword', 'breaking_default', 'Palabras Urgente', ['urgente', 'breaking', 'última hora', 'ultima hora', 'oficial']],
        ];

        foreach ($entries as [$type, $key, $label, $aliases]) {
            NewsDictionaryEntry::updateOrCreate(
                ['key' => $key],
                [
                    'type' => NewsDictionaryType::from($type),
                    'label' => $label,
                    'aliases' => $aliases,
                    'is_active' => true,
                ],
            );
        }
    }
};
