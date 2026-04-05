<?php

use App\Enums\NewsDictionaryType;
use App\Models\NewsDictionaryEntry;
use App\Services\ArticleCategorizationService;

beforeEach(function () {
    $this->service = new ArticleCategorizationService;

    // Seed minimal dictionary entries needed for tests
    $entries = [
        ['type' => NewsDictionaryType::Team, 'key' => 'real_madrid', 'label' => 'Real Madrid', 'aliases' => ['real madrid', 'madrid', 'merengues']],
        ['type' => NewsDictionaryType::Team, 'key' => 'atletico_madrid', 'label' => 'Atlético de Madrid', 'aliases' => ['atlético de madrid', 'atletico de madrid', 'atleti']],
        ['type' => NewsDictionaryType::Team, 'key' => 'barcelona', 'label' => 'FC Barcelona', 'aliases' => ['barcelona', 'barça', 'barca']],
        ['type' => NewsDictionaryType::Team, 'key' => 'liverpool', 'label' => 'Liverpool', 'aliases' => ['liverpool', 'reds']],
        ['type' => NewsDictionaryType::Team, 'key' => 'arsenal', 'label' => 'Arsenal', 'aliases' => ['arsenal', 'gunners']],
        ['type' => NewsDictionaryType::Team, 'key' => 'atletico_nacional', 'label' => 'Atlético Nacional', 'aliases' => ['atlético nacional', 'atletico nacional', 'nacional']],
        ['type' => NewsDictionaryType::Team, 'key' => 'millonarios_fc', 'label' => 'Millonarios FC', 'aliases' => ['millonarios fc', 'millonarios']],
        ['type' => NewsDictionaryType::Competition, 'key' => 'champions_league', 'label' => 'Champions League', 'aliases' => ['champions league', 'champions']],
        ['type' => NewsDictionaryType::Competition, 'key' => 'la_liga', 'label' => 'La Liga', 'aliases' => ['la liga', 'laliga']],
        ['type' => NewsDictionaryType::Competition, 'key' => 'liga_betplay', 'label' => 'Liga BetPlay', 'aliases' => ['liga betplay', 'betplay']],
        ['type' => NewsDictionaryType::Topic, 'key' => 'transfers', 'label' => 'Fichajes', 'aliases' => ['fichaje', 'fichajes', 'traspaso']],
        ['type' => NewsDictionaryType::BreakingKeyword, 'key' => 'breaking_default', 'label' => 'Urgente', 'aliases' => ['urgente', 'última hora', 'ultima hora', 'oficial']],
    ];

    foreach ($entries as $entry) {
        NewsDictionaryEntry::create([...$entry, 'is_active' => true]);
    }

    NewsDictionaryEntry::clearCache();
});

test('categorizes article by team name', function () {
    $result = $this->service->categorize([
        'title' => 'Real Madrid vence 3-1 al Atletico de Madrid en el derbi',
        'snippet' => 'Vinicius Jr marcó doblete en la victoria merengue.',
    ]);

    expect($result['teams'])->toContain('real_madrid');
    expect($result['teams'])->toContain('atletico_madrid');
});

test('categorizes article by competition', function () {
    $result = $this->service->categorize([
        'title' => 'Sorteo de Champions League: los cruces de cuartos de final',
        'snippet' => null,
    ]);

    expect($result['competitions'])->toContain('champions_league');
});

test('categorizes article by topic', function () {
    $result = $this->service->categorize([
        'title' => 'Barcelona cierra fichaje de estrella del Liverpool',
        'snippet' => 'El traspaso se cierra por 80 millones de euros.',
    ]);

    expect($result['topics'])->toContain('transfers');
    expect($result['teams'])->toContain('barcelona');
    expect($result['teams'])->toContain('liverpool');
});

test('detects breaking news', function () {
    $result = $this->service->categorize([
        'title' => 'ÚLTIMA HORA: Messi confirma su retiro del fútbol',
        'snippet' => null,
    ]);

    expect($result['is_breaking'])->toBeTrue();
});

test('non-breaking news is not marked as breaking', function () {
    $result = $this->service->categorize([
        'title' => 'Resumen de la jornada 15 de La Liga',
        'snippet' => null,
    ]);

    expect($result['is_breaking'])->toBeFalse();
});

test('calculates title similarity correctly for similar titles', function () {
    $similarity = $this->service->calculateTitleSimilarity(
        'Vinicius anota doblete en victoria del Real Madrid ante Atletico',
        'Real Madrid golea al Atletico con doblete de Vinicius',
    );

    expect($similarity)->toBeGreaterThan(0.4);
});

test('calculates title similarity correctly for different titles', function () {
    $similarity = $this->service->calculateTitleSimilarity(
        'Barcelona presenta nueva camiseta para la Champions League',
        'Real Madrid vence 3-1 al Atletico en el derbi madrileño',
    );

    expect($similarity)->toBeLessThan(0.2);
});

test('categorizes colombian league articles', function () {
    $result = $this->service->categorize([
        'title' => 'Atlético Nacional gana la Liga BetPlay tras vencer a Millonarios',
        'snippet' => 'El verdolaga se coronó campeón en el estadio Atanasio Girardot.',
    ]);

    expect($result['teams'])->toContain('atletico_nacional');
    expect($result['teams'])->toContain('millonarios_fc');
    expect($result['competitions'])->toContain('liga_betplay');
});
