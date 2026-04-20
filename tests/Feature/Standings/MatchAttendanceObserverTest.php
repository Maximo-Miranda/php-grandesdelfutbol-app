<?php

use App\Enums\AttendanceRole;
use App\Enums\AttendanceStatus;
use App\Enums\AttendanceTeam;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;

test('confirming attendance with team adds the player to the team roster', function () {
    $club = Club::factory()->create();
    $season = Season::factory()->create(['club_id' => $club->id]);
    $teamA = Team::factory()->create(['club_id' => $club->id, 'season_id' => $season->id]);
    $teamB = Team::factory()->create(['club_id' => $club->id, 'season_id' => $season->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'team_a_id' => $teamA->id,
        'team_b_id' => $teamB->id,
    ]);

    MatchAttendance::query()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
        'status' => AttendanceStatus::Confirmed,
        'role' => AttendanceRole::Starter,
        'team' => AttendanceTeam::A,
        'confirmed_at' => now(),
    ]);

    expect($teamA->fresh()->players->pluck('id')->all())->toContain($player->id);
    expect($teamB->fresh()->players->pluck('id')->all())->not->toContain($player->id);
});

test('confirming without team does nothing to roster', function () {
    $club = Club::factory()->create();
    $season = Season::factory()->create(['club_id' => $club->id]);
    $teamA = Team::factory()->create(['club_id' => $club->id, 'season_id' => $season->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'team_a_id' => $teamA->id,
    ]);

    MatchAttendance::query()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
        'status' => AttendanceStatus::Confirmed,
        'role' => AttendanceRole::Starter,
        'team' => null,
        'confirmed_at' => now(),
    ]);

    expect($teamA->fresh()->players->count())->toBe(0);
});
