<?php

use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\Season;
use App\Models\Team;
use App\Services\StandingsService;

beforeEach(function () {
    $this->service = app(StandingsService::class);
    $this->club = Club::factory()->create();
    $this->season = Season::factory()->create(['club_id' => $this->club->id]);
});

function makeTeam(int $clubId, int $seasonId, string $name): Team
{
    return Team::factory()->create([
        'club_id' => $clubId,
        'season_id' => $seasonId,
        'name' => $name,
        'color' => '#dc2626',
    ]);
}

function makeCompletedMatch(int $clubId, int $seasonId, Team $a, Team $b, int $scoreA, int $scoreB, bool $friendly = false): FootballMatch
{
    return FootballMatch::factory()->completed()->create([
        'club_id' => $clubId,
        'season_id' => $seasonId,
        'team_a_id' => $a->id,
        'team_b_id' => $b->id,
        'team_a_score' => $scoreA,
        'team_b_score' => $scoreB,
        'is_friendly' => $friendly,
        'scheduled_at' => now()->subDays(rand(1, 30)),
    ]);
}

test('standings rank teams by points then goal difference', function () {
    $alpha = makeTeam($this->club->id, $this->season->id, 'Alpha');
    $bravo = makeTeam($this->club->id, $this->season->id, 'Bravo');
    $charlie = makeTeam($this->club->id, $this->season->id, 'Charlie');

    makeCompletedMatch($this->club->id, $this->season->id, $alpha, $bravo, 3, 0);
    makeCompletedMatch($this->club->id, $this->season->id, $alpha, $charlie, 1, 1);
    makeCompletedMatch($this->club->id, $this->season->id, $bravo, $charlie, 2, 0);

    $table = $this->service->forSeason($this->season->fresh());

    expect($table->pluck('name')->all())->toBe(['Alpha', 'Bravo', 'Charlie']);
    expect($table->first()['Pts'])->toBe(4);
});

test('friendly matches are excluded from standings computation but show F in last 5', function () {
    $alpha = makeTeam($this->club->id, $this->season->id, 'Alpha');
    $bravo = makeTeam($this->club->id, $this->season->id, 'Bravo');

    makeCompletedMatch($this->club->id, $this->season->id, $alpha, $bravo, 2, 1);
    makeCompletedMatch($this->club->id, $this->season->id, $alpha, $bravo, 0, 5, friendly: true);

    $table = $this->service->forSeason($this->season->fresh());
    $alphaRow = $table->firstWhere('name', 'Alpha');

    expect($alphaRow['PJ'])->toBe(1);
    expect($alphaRow['G'])->toBe(1);
    expect($alphaRow['last5'])->toContain('F');
    expect($alphaRow['last5'])->toContain('W');
});

test('matches without both teams are ignored', function () {
    $alpha = makeTeam($this->club->id, $this->season->id, 'Alpha');

    FootballMatch::factory()->completed()->create([
        'club_id' => $this->club->id,
        'season_id' => $this->season->id,
        'team_a_id' => $alpha->id,
        'team_b_id' => null,
        'team_a_score' => 3,
        'team_b_score' => null,
        'is_friendly' => false,
    ]);

    $table = $this->service->forSeason($this->season->fresh());
    $alphaRow = $table->firstWhere('name', 'Alpha');

    expect($alphaRow['PJ'])->toBe(0);
});
