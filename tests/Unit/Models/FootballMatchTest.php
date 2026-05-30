<?php

use App\Enums\AttendanceTeam;
use App\Enums\MatchStatus;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use Carbon\CarbonImmutable;

/**
 * @return array{0: FootballMatch, 1: Team, 2: Team}
 */
function tournamentMatch(): array
{
    $club = Club::factory()->create();
    $season = Season::factory()->create(['club_id' => $club->id]);

    $teamA = Team::factory()->create(['club_id' => $club->id, 'season_id' => $season->id, 'is_tournament' => true]);
    $teamB = Team::factory()->create(['club_id' => $club->id, 'season_id' => $season->id, 'is_tournament' => true]);

    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'team_a_id' => $teamA->id,
        'team_b_id' => $teamB->id,
    ]);

    return [$match, $teamA, $teamB];
}

test('match belongs to a club', function () {
    $match = FootballMatch::factory()->create();
    expect($match->club)->toBeInstanceOf(Club::class);
});

test('match has many attendances', function () {
    $match = FootballMatch::factory()->create();
    MatchAttendance::factory()->count(3)->create(['match_id' => $match->id]);

    expect($match->attendances)->toHaveCount(3);
});

test('match casts status to enum', function () {
    $match = FootballMatch::factory()->create();
    expect($match->status)->toBe(MatchStatus::Upcoming);
});

test('upcoming scope returns only upcoming matches', function () {
    FootballMatch::factory()->create();
    FootballMatch::factory()->completed()->create();

    expect(FootballMatch::query()->upcoming()->count())->toBe(1);
});

test('completed scope returns only completed matches', function () {
    FootballMatch::factory()->create();
    FootballMatch::factory()->completed()->create();

    expect(FootballMatch::query()->completed()->count())->toBe(1);
});

test('match casts datetime fields', function () {
    $match = FootballMatch::factory()->create(['scheduled_at' => now()]);
    expect($match->scheduled_at)->toBeInstanceOf(CarbonImmutable::class);
});

test('tournament: a player in team A is assigned to A, ignoring a rival preference', function () {
    [$match, $teamA] = tournamentMatch();
    $player = Player::factory()->create(['club_id' => $match->club_id]);
    $teamA->players()->attach($player->id);

    expect($match->resolveTeamForPlayer($player, AttendanceTeam::B))->toBe(AttendanceTeam::A)
        ->and($match->resolveTeamForPlayer($player, null))->toBe(AttendanceTeam::A);
});

test('tournament: a player in team B is assigned to B, ignoring a rival preference', function () {
    [$match, , $teamB] = tournamentMatch();
    $player = Player::factory()->create(['club_id' => $match->club_id]);
    $teamB->players()->attach($player->id);

    expect($match->resolveTeamForPlayer($player, AttendanceTeam::A))->toBe(AttendanceTeam::B);
});

test('tournament: a player in no roster is not resolved to any team', function () {
    [$match] = tournamentMatch();
    $player = Player::factory()->create(['club_id' => $match->club_id]);

    expect($match->resolveTeamForPlayer($player, AttendanceTeam::A))->toBeNull();
});
