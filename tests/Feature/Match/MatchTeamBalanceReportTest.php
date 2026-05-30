<?php

use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\Player;
use App\Services\MatchService;

test('balance report sums starter scores per team and computes delta', function () {
    $service = app(MatchService::class);
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $strongPlayer = Player::factory()->create([
        'club_id' => $club->id,
        'matches_played' => 20,
        'goals' => 18,
        'assists' => 10,
    ]);
    $weakPlayer = Player::factory()->create([
        'club_id' => $club->id,
        'matches_played' => 20,
        'goals' => 1,
        'assists' => 0,
    ]);

    MatchAttendance::factory()->teamA()->starter()->create([
        'match_id' => $match->id,
        'player_id' => $strongPlayer->id,
    ]);
    MatchAttendance::factory()->teamB()->starter()->create([
        'match_id' => $match->id,
        'player_id' => $weakPlayer->id,
    ]);

    $report = $service->teamBalanceReport($match);

    expect($report['team_a_score'])->toBeGreaterThan($report['team_b_score'])
        ->and($report['heavier_team'])->toBe('a')
        ->and($report['outliers'])->not->toBeEmpty()
        ->and($report['outliers'][0]['attendance']->player_id)->toBe($strongPlayer->id);
});

test('balance report returns zero delta for equal teams', function () {
    $service = app(MatchService::class);
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $playerA = Player::factory()->create([
        'club_id' => $club->id,
        'matches_played' => 0,
        'goals' => 0,
        'assists' => 0,
    ]);
    $playerB = Player::factory()->create([
        'club_id' => $club->id,
        'matches_played' => 0,
        'goals' => 0,
        'assists' => 0,
    ]);

    MatchAttendance::factory()->teamA()->starter()->create([
        'match_id' => $match->id,
        'player_id' => $playerA->id,
    ]);
    MatchAttendance::factory()->teamB()->starter()->create([
        'match_id' => $match->id,
        'player_id' => $playerB->id,
    ]);

    $report = $service->teamBalanceReport($match);

    expect($report['delta_pct'])->toBeFloat()
        ->and($report)->toHaveKeys(['team_a_score', 'team_b_score', 'delta_pct', 'heavier_team', 'outliers']);
});

test('balance report ignores substitutes and unassigned players', function () {
    $service = app(MatchService::class);
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);

    $starterA = Player::factory()->create(['club_id' => $club->id, 'matches_played' => 5, 'goals' => 2]);
    $subA = Player::factory()->create(['club_id' => $club->id, 'matches_played' => 5, 'goals' => 50]);

    MatchAttendance::factory()->teamA()->starter()->create([
        'match_id' => $match->id,
        'player_id' => $starterA->id,
    ]);
    MatchAttendance::factory()->teamA()->create([
        'match_id' => $match->id,
        'player_id' => $subA->id,
        'role' => 'substitute',
    ]);

    $report = $service->teamBalanceReport($match);

    expect($report['team_b_score'])->toBe(0.0);
});
