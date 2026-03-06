<?php

use App\Enums\AttendanceRole;
use App\Enums\AttendanceStatus;
use App\Enums\AttendanceTeam;
use App\Enums\PlayerPosition;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\Player;
use App\Services\MatchService;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->match = FootballMatch::factory()->create([
        'club_id' => $this->club->id,
        'max_players' => 14,
        'max_substitutes' => 4,
    ]);
    $this->service = app(MatchService::class);
});

it('distributes confirmed players into two balanced teams', function () {
    $positions = [
        PlayerPosition::Gk, PlayerPosition::Cb, PlayerPosition::Lb, PlayerPosition::Cm,
        PlayerPosition::Cdm, PlayerPosition::Cam, PlayerPosition::St,
        PlayerPosition::Gk, PlayerPosition::Rb, PlayerPosition::Cb, PlayerPosition::Cm,
        PlayerPosition::Cam, PlayerPosition::Lw, PlayerPosition::St,
    ];

    foreach ($positions as $pos) {
        $player = Player::factory()->withStats()->create([
            'club_id' => $this->club->id,
            'position' => $pos,
        ]);

        MatchAttendance::factory()->create([
            'match_id' => $this->match->id,
            'player_id' => $player->id,
            'status' => AttendanceStatus::Confirmed,
            'role' => AttendanceRole::Pending,
        ]);
    }

    $this->service->autoAssignTeams($this->match);

    $attendances = $this->match->attendances()->get();
    $teamA = $attendances->where('team', AttendanceTeam::A);
    $teamB = $attendances->where('team', AttendanceTeam::B);

    expect($teamA)->toHaveCount(7);
    expect($teamB)->toHaveCount(7);
    expect($attendances->where('role', AttendanceRole::Starter))->toHaveCount(14);
});

it('marks excess players as substitutes', function () {
    for ($i = 0; $i < 16; $i++) {
        $player = Player::factory()->withStats()->create([
            'club_id' => $this->club->id,
        ]);

        MatchAttendance::factory()->create([
            'match_id' => $this->match->id,
            'player_id' => $player->id,
            'status' => AttendanceStatus::Confirmed,
        ]);
    }

    $this->service->autoAssignTeams($this->match);

    $attendances = $this->match->attendances()->get();
    $starters = $attendances->where('role', AttendanceRole::Starter);
    $subs = $attendances->where('role', AttendanceRole::Substitute);

    expect($starters)->toHaveCount(14);
    expect($subs)->toHaveCount(2);
    expect($subs->whereNull('team'))->toHaveCount(2);
});

it('handles players without stats by assigning randomly', function () {
    for ($i = 0; $i < 10; $i++) {
        $player = Player::factory()->create([
            'club_id' => $this->club->id,
            'goals' => 0,
            'assists' => 0,
            'matches_played' => 0,
        ]);

        MatchAttendance::factory()->create([
            'match_id' => $this->match->id,
            'player_id' => $player->id,
            'status' => AttendanceStatus::Confirmed,
        ]);
    }

    $this->service->autoAssignTeams($this->match);

    $attendances = $this->match->attendances()->get();
    $teamA = $attendances->where('team', AttendanceTeam::A);
    $teamB = $attendances->where('team', AttendanceTeam::B);

    expect($teamA)->toHaveCount(5);
    expect($teamB)->toHaveCount(5);
});

it('resets previous team assignments before re-sorting', function () {
    for ($i = 0; $i < 10; $i++) {
        $player = Player::factory()->withStats()->create([
            'club_id' => $this->club->id,
        ]);

        MatchAttendance::factory()->create([
            'match_id' => $this->match->id,
            'player_id' => $player->id,
            'status' => AttendanceStatus::Confirmed,
            'team' => $i < 5 ? AttendanceTeam::A : AttendanceTeam::B,
            'role' => AttendanceRole::Starter,
        ]);
    }

    $this->service->autoAssignTeams($this->match);

    $attendances = $this->match->attendances()->get();
    $teamA = $attendances->where('team', AttendanceTeam::A);
    $teamB = $attendances->where('team', AttendanceTeam::B);

    expect($teamA)->toHaveCount(5);
    expect($teamB)->toHaveCount(5);
});

it('does not assign declined players to teams', function () {
    for ($i = 0; $i < 6; $i++) {
        $player = Player::factory()->withStats()->create(['club_id' => $this->club->id]);
        MatchAttendance::factory()->create([
            'match_id' => $this->match->id,
            'player_id' => $player->id,
            'status' => AttendanceStatus::Confirmed,
        ]);
    }

    for ($i = 0; $i < 2; $i++) {
        $player = Player::factory()->withStats()->create(['club_id' => $this->club->id]);
        MatchAttendance::factory()->declined()->create([
            'match_id' => $this->match->id,
            'player_id' => $player->id,
        ]);
    }

    $this->service->autoAssignTeams($this->match);

    $attendances = $this->match->attendances()->get();
    $assigned = $attendances->whereNotNull('team');
    $declined = $attendances->where('status', AttendanceStatus::Declined);

    expect($assigned)->toHaveCount(6);
    expect($declined->whereNull('team'))->toHaveCount(2);
});
