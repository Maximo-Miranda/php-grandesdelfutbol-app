<?php

use App\Enums\SeasonStatus;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\Season;
use App\Models\Team;
use App\Services\SeasonService;

beforeEach(function () {
    $this->service = app(SeasonService::class);
});

test('activeFor creates an active season when none exists', function () {
    $club = Club::factory()->create();

    $season = $this->service->activeFor($club);

    expect($season)->toBeInstanceOf(Season::class);
    expect($season->club_id)->toBe($club->id);
    expect($season->status)->toBe(SeasonStatus::Active);
    expect($season->matches_count)->toBe(Season::DEFAULT_MATCHES_COUNT);
    expect($season->name)->toBe('Temporada #1');
});

test('activeFor returns the same active season on repeated calls', function () {
    $club = Club::factory()->create();

    $first = $this->service->activeFor($club);
    $second = $this->service->activeFor($club);

    expect($first->id)->toBe($second->id);
    expect(Season::query()->where('club_id', $club->id)->count())->toBe(1);
});

test('finalizeIfComplete marks season completed when matches_count is reached', function () {
    $club = Club::factory()->create();
    $season = Season::factory()->create(['club_id' => $club->id, 'matches_count' => 3]);
    $teamA = Team::factory()->forSeason($season)->create();
    $teamB = Team::factory()->forSeason($season)->create();

    FootballMatch::factory()->count(3)->completed()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'team_a_id' => $teamA->id,
        'team_b_id' => $teamB->id,
        'team_a_score' => 1,
        'team_b_score' => 0,
        'is_friendly' => false,
    ]);

    $this->service->finalizeIfComplete($season->fresh());

    expect($season->fresh()->status)->toBe(SeasonStatus::Completed);
});

test('friendly and cancelled matches do not count toward season completion', function () {
    $club = Club::factory()->create();
    $season = Season::factory()->create(['club_id' => $club->id, 'matches_count' => 3]);

    FootballMatch::factory()->count(2)->completed()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'is_friendly' => false,
    ]);

    FootballMatch::factory()->completed()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'is_friendly' => true,
    ]);

    FootballMatch::factory()->cancelled()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'is_friendly' => false,
    ]);

    $this->service->finalizeIfComplete($season->fresh());

    expect($season->fresh()->status)->toBe(SeasonStatus::Active);
});

test('creating a match auto-assigns the active season', function () {
    $club = Club::factory()->create();

    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    expect($match->season_id)->not->toBeNull();
    $season = Season::find($match->season_id);
    expect($season->club_id)->toBe($club->id);
    expect($season->isActive())->toBeTrue();
});
