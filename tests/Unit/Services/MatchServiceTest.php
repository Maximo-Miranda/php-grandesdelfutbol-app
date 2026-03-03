<?php

use App\Enums\AttendanceRole;
use App\Enums\AttendanceStatus;
use App\Enums\AttendanceTeam;
use App\Enums\MatchStatus;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\Player;
use App\Services\MatchService;

test('createMatch creates a match with defaults', function () {
    $club = Club::factory()->create();
    $service = new MatchService;

    $match = $service->createMatch($club, [
        'title' => 'Sunday Match',
        'scheduled_at' => now()->addDay()->toISOString(),
    ]);

    expect($match->title)->toBe('Sunday Match')
        ->and($match->club_id)->toBe($club->id)
        ->and($match->status)->toBe(MatchStatus::Upcoming)
        ->and($match->share_token)->not->toBeNull()
        ->and($match->duration_minutes)->toBe(60)
        ->and($match->max_players)->toBe(10)
        ->and($match->team_a_name)->toBe('Equipo A')
        ->and($match->team_b_name)->toBe('Equipo B')
        ->and($match->team_a_color)->toBe('#1a1a1a')
        ->and($match->team_b_color)->toBe('#facc15');
});

test('createMatch accepts team config', function () {
    $club = Club::factory()->create();
    $service = new MatchService;

    $match = $service->createMatch($club, [
        'title' => 'Custom Teams',
        'scheduled_at' => now()->addDay()->toISOString(),
        'team_a_name' => 'Los Rojos',
        'team_b_name' => 'Los Azules',
        'team_a_color' => '#FF0000',
        'team_b_color' => '#0000FF',
    ]);

    expect($match->team_a_name)->toBe('Los Rojos')
        ->and($match->team_b_name)->toBe('Los Azules')
        ->and($match->team_a_color)->toBe('#FF0000')
        ->and($match->team_b_color)->toBe('#0000FF');
});

test('registerPlayer auto-assigns starter role when under max_players', function () {
    $match = FootballMatch::factory()->create(['max_players' => 10]);
    $player = Player::factory()->create(['club_id' => $match->club_id]);
    $service = new MatchService;

    $attendance = $service->registerPlayer($match, $player, AttendanceStatus::Confirmed);

    expect($attendance->status)->toBe(AttendanceStatus::Confirmed)
        ->and($attendance->role)->toBe(AttendanceRole::Starter)
        ->and($attendance->confirmed_at)->not->toBeNull();
});

test('registerPlayer auto-assigns substitute role when at max_players', function () {
    $match = FootballMatch::factory()->create(['max_players' => 2]);
    $service = new MatchService;

    // Fill up starters
    $players = Player::factory()->count(2)->create(['club_id' => $match->club_id]);
    foreach ($players as $player) {
        $service->registerPlayer($match, $player, AttendanceStatus::Confirmed);
    }

    // Next player should be substitute
    $extraPlayer = Player::factory()->create(['club_id' => $match->club_id]);
    $attendance = $service->registerPlayer($match, $extraPlayer, AttendanceStatus::Confirmed);

    expect($attendance->role)->toBe(AttendanceRole::Substitute);
});

test('registerPlayer resets role and team when declining', function () {
    $match = FootballMatch::factory()->create();
    $player = Player::factory()->create(['club_id' => $match->club_id]);
    $service = new MatchService;

    // First confirm with team
    $service->registerPlayer($match, $player, AttendanceStatus::Confirmed, AttendanceTeam::A);

    // Then decline
    $attendance = $service->registerPlayer($match, $player, AttendanceStatus::Declined);

    expect($attendance->status)->toBe(AttendanceStatus::Declined)
        ->and($attendance->role)->toBe(AttendanceRole::Pending)
        ->and($attendance->team)->toBeNull()
        ->and($attendance->confirmed_at)->toBeNull();

    // Should not duplicate
    expect(MatchAttendance::query()->where('match_id', $match->id)->count())->toBe(1);
});

test('registerPlayer saves team choice', function () {
    $match = FootballMatch::factory()->create();
    $player = Player::factory()->create(['club_id' => $match->club_id]);
    $service = new MatchService;

    $attendance = $service->registerPlayer($match, $player, AttendanceStatus::Confirmed, AttendanceTeam::B);

    expect($attendance->team)->toBe(AttendanceTeam::B);
});

test('isRegistrationOpen checks status and timing', function () {
    $service = new MatchService;

    $upcomingMatch = FootballMatch::factory()->create([
        'scheduled_at' => now()->addHours(12),
        'registration_opens_hours' => 24,
    ]);
    expect($service->isRegistrationOpen($upcomingMatch))->toBeTrue();

    $completedMatch = FootballMatch::factory()->completed()->create();
    expect($service->isRegistrationOpen($completedMatch))->toBeFalse();

    $futureMatch = FootballMatch::factory()->create([
        'scheduled_at' => now()->addDays(5),
        'registration_opens_hours' => 24,
    ]);
    expect($service->isRegistrationOpen($futureMatch))->toBeFalse();
});

test('autoAssignTeams distributes players into teams', function () {
    $match = FootballMatch::factory()->create(['max_players' => 6]);
    $players = Player::factory()->count(6)->create(['club_id' => $match->club_id]);

    foreach ($players as $player) {
        MatchAttendance::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'status' => AttendanceStatus::Confirmed,
        ]);
    }

    $service = new MatchService;
    $service->autoAssignTeams($match);

    $match->load('attendances');
    $teamA = $match->attendances->where('team', 'a')->count();
    $teamB = $match->attendances->where('team', 'b')->count();

    expect($teamA)->toBe(3)
        ->and($teamB)->toBe(3);

    $starters = $match->attendances->where('role', AttendanceRole::Starter)->count();
    expect($starters)->toBe(6);
});

test('autoAssignTeams respects existing team choices', function () {
    $match = FootballMatch::factory()->create(['max_players' => 6]);
    $players = Player::factory()->count(6)->create(['club_id' => $match->club_id]);

    // Two players already chose teams
    MatchAttendance::factory()->create([
        'match_id' => $match->id,
        'player_id' => $players[0]->id,
        'status' => AttendanceStatus::Confirmed,
        'team' => AttendanceTeam::A,
    ]);
    MatchAttendance::factory()->create([
        'match_id' => $match->id,
        'player_id' => $players[1]->id,
        'status' => AttendanceStatus::Confirmed,
        'team' => AttendanceTeam::B,
    ]);

    // Rest are unassigned
    for ($i = 2; $i < 6; $i++) {
        MatchAttendance::factory()->create([
            'match_id' => $match->id,
            'player_id' => $players[$i]->id,
            'status' => AttendanceStatus::Confirmed,
        ]);
    }

    $service = new MatchService;
    $service->autoAssignTeams($match);

    $match->load('attendances');

    // The pre-assigned players should keep their teams
    $p0 = $match->attendances->where('player_id', $players[0]->id)->first();
    $p1 = $match->attendances->where('player_id', $players[1]->id)->first();

    expect($p0->team)->toBe(AttendanceTeam::A)
        ->and($p1->team)->toBe(AttendanceTeam::B);

    // All 6 should be assigned
    expect($match->attendances->whereNotNull('team')->count())->toBe(6);
});
