<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

#[Model('gemini-2.5-flash-lite')]
class ExtractNewsPreferences implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
        Eres un asistente que extrae preferencias de fútbol a partir de texto libre escrito por un usuario hispanohablante.

        Tu tarea:
        1. Extraer nombres de equipos de fútbol mencionados
        2. Extraer ligas, torneos o competiciones mencionadas
        3. Extraer temas de interés (fichajes, lesiones, resultados, etc.)

        Reglas de normalización:
        - Normaliza nombres a formato snake_case en inglés/español: "Barca" → "barcelona", "la Champions" → "champions_league"
        - Usa identificadores estándar: real_madrid, barcelona, atletico_madrid, liverpool, manchester_city, etc.
        - Para ligas: la_liga, premier_league, champions_league, serie_a, bundesliga, liga_betplay, copa_libertadores, liga_mx, etc.
        - Para temas: transfers, injuries, results, tactics, coaches, referees, youth
        - Si no puedes identificar una entidad con certeza, omítela
        - Si el texto no contiene preferencias claras, retorna arrays vacíos
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'teams' => $schema->array()->items($schema->string())->required(),
            'competitions' => $schema->array()->items($schema->string())->required(),
            'topics' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
