<?php

use App\Enums\AttendanceRole;
use App\Enums\AttendanceStatus;
use App\Enums\AttendanceTeam;
use App\Enums\MatchStatus;
use App\Enums\PlayerPosition;
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
        'team_a_color' => '#dc2626',
        'team_b_color' => '#2563eb',
    ]);

    expect($match->team_a_name)->toBe('Los Rojos')
        ->and($match->team_b_name)->toBe('Los Azules')
        ->and($match->team_a_color)->toBe('#dc2626')
        ->and($match->team_b_color)->toBe('#2563eb');
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

test('registerPlayer auto-assigns substitute role when team starters are full', function () {
    $match = FootballMatch::factory()->create(['max_players' => 4]); // 2 per team
    $service = new MatchService;

    // Fill up team A starters (2 max) with outfield players
    $players = Player::factory()->count(2)->create(['club_id' => $match->club_id, 'position' => PlayerPosition::Cm]);
    foreach ($players as $player) {
        $service->registerPlayer($match, $player, AttendanceStatus::Confirmed, AttendanceTeam::A);
    }

    // Next outfield player on team A should be substitute
    $extraPlayer = Player::factory()->create(['club_id' => $match->club_id, 'position' => PlayerPosition::St]);
    $attendance = $service->registerPlayer($match, $extraPlayer, AttendanceStatus::Confirmed, AttendanceTeam::A);

    expect($attendance->role)->toBe(AttendanceRole::Substitute);
});

test('registerPlayer assigns starter when other team is full but this team has room', function () {
    $match = FootballMatch::factory()->create(['max_players' => 4]); // 2 per team
    $service = new MatchService;

    // Fill up team A starters
    $playersA = Player::factory()->count(2)->create(['club_id' => $match->club_id]);
    foreach ($playersA as $player) {
        $service->registerPlayer($match, $player, AttendanceStatus::Confirmed, AttendanceTeam::A);
    }

    // Team B should still get starter role
    $playerB = Player::factory()->create(['club_id' => $match->club_id]);
    $attendance = $service->registerPlayer($match, $playerB, AttendanceStatus::Confirmed, AttendanceTeam::B);

    expect($attendance->role)->toBe(AttendanceRole::Starter);
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

test('recalculateRoles fixes roles per team based on confirmed_at order', function () {
    $match = FootballMatch::factory()->create(['max_players' => 4]); // 2 per team
    $service = new MatchService;

    // Create 3 outfield players on team A — all incorrectly marked as starters
    $players = Player::factory()->count(3)->create(['club_id' => $match->club_id, 'position' => PlayerPosition::Cm]);
    foreach ($players as $i => $player) {
        MatchAttendance::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'status' => AttendanceStatus::Confirmed,
            'team' => AttendanceTeam::A,
            'role' => AttendanceRole::Starter, // all incorrectly starters
            'confirmed_at' => now()->addMinutes($i),
        ]);
    }

    $service->recalculateRoles($match);

    $match->load('attendances');
    $teamA = $match->attendances->where('team', AttendanceTeam::A)->sortBy('confirmed_at')->values();

    expect($teamA[0]->role)->toBe(AttendanceRole::Starter)
        ->and($teamA[1]->role)->toBe(AttendanceRole::Starter)
        ->and($teamA[2]->role)->toBe(AttendanceRole::Substitute);
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

test('autoAssignTeams re-sorts everyone based on confirmation order', function () {
    // The new contract: autoAssign always re-sorts. Pre-assigned teams from a
    // previous draft are not preserved — admins use swap to fine-tune.
    $match = FootballMatch::factory()->create(['max_players' => 6]);
    $players = Player::factory()->count(6)->create(['club_id' => $match->club_id]);

    foreach ($players as $i => $player) {
        MatchAttendance::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'status' => AttendanceStatus::Confirmed,
            'team' => $i < 2 ? ($i === 0 ? AttendanceTeam::A : AttendanceTeam::B) : null,
        ]);
    }

    $service = new MatchService;
    $service->autoAssignTeams($match);

    $match->load('attendances');

    expect($match->attendances->whereNotNull('team')->count())->toBe(6)
        ->and($match->attendances->where('team', AttendanceTeam::A)->count())->toBe(3)
        ->and($match->attendances->where('team', AttendanceTeam::B)->count())->toBe(3);
});

// --- Goalkeeper priority tests ---

test('goalkeeper becomes starter when team is full and no other GK on team', function () {
    $match = FootballMatch::factory()->create(['max_players' => 4]); // 2 per team
    $service = new MatchService;

    // Fill team A with 2 outfield starters
    $outfield1 = Player::factory()->create(['club_id' => $match->club_id, 'position' => PlayerPosition::Cm]);
    $outfield2 = Player::factory()->create(['club_id' => $match->club_id, 'position' => PlayerPosition::St]);

    $service->registerPlayer($match, $outfield1, AttendanceStatus::Confirmed, AttendanceTeam::A);
    $service->registerPlayer($match, $outfield2, AttendanceStatus::Confirmed, AttendanceTeam::A);

    // GK confirms on team A (team full)
    $gk = Player::factory()->goalkeeper()->create(['club_id' => $match->club_id]);
    $attendance = $service->registerPlayer($match, $gk, AttendanceStatus::Confirmed, AttendanceTeam::A);

    expect($attendance->role)->toBe(AttendanceRole::Starter);

    // The last outfield player should be demoted
    $demoted = MatchAttendance::where('player_id', $outfield2->id)->first();
    expect($demoted->role)->toBe(AttendanceRole::Substitute);

    // First outfield player stays starter
    $first = MatchAttendance::where('player_id', $outfield1->id)->first();
    expect($first->role)->toBe(AttendanceRole::Starter);
});

test('goalkeeper gets starter normally when team has room', function () {
    $match = FootballMatch::factory()->create(['max_players' => 4]); // 2 per team
    $service = new MatchService;

    // Only 1 player on team A
    $outfield = Player::factory()->create(['club_id' => $match->club_id, 'position' => PlayerPosition::Cm]);
    $service->registerPlayer($match, $outfield, AttendanceStatus::Confirmed, AttendanceTeam::A);

    // GK confirms — room available
    $gk = Player::factory()->goalkeeper()->create(['club_id' => $match->club_id]);
    $attendance = $service->registerPlayer($match, $gk, AttendanceStatus::Confirmed, AttendanceTeam::A);

    expect($attendance->role)->toBe(AttendanceRole::Starter);

    // No one was demoted
    $outfieldAtt = MatchAttendance::where('player_id', $outfield->id)->first();
    expect($outfieldAtt->role)->toBe(AttendanceRole::Starter);
});

test('goalkeeper gets substitute when another GK already starter on same team', function () {
    $match = FootballMatch::factory()->create(['max_players' => 4, 'max_substitutes' => 4]); // 2 per team
    $service = new MatchService;

    // GK1 confirms on team A as starter
    $gk1 = Player::factory()->goalkeeper()->create(['club_id' => $match->club_id]);
    $service->registerPlayer($match, $gk1, AttendanceStatus::Confirmed, AttendanceTeam::A);

    // Fill remaining starter slot
    $outfield = Player::factory()->create(['club_id' => $match->club_id, 'position' => PlayerPosition::Cm]);
    $service->registerPlayer($match, $outfield, AttendanceStatus::Confirmed, AttendanceTeam::A);

    // GK2 confirms — another GK already exists as starter
    $gk2 = Player::factory()->goalkeeper()->create(['club_id' => $match->club_id]);
    $attendance = $service->registerPlayer($match, $gk2, AttendanceStatus::Confirmed, AttendanceTeam::A);

    expect($attendance->role)->toBe(AttendanceRole::Substitute);
});

test('goalkeeper priority does not apply when team is null', function () {
    $match = FootballMatch::factory()->create(['max_players' => 2, 'max_substitutes' => 2]);
    $service = new MatchService;

    // Fill starters without team
    $outfield1 = Player::factory()->create(['club_id' => $match->club_id, 'position' => PlayerPosition::Cm]);
    $outfield2 = Player::factory()->create(['club_id' => $match->club_id, 'position' => PlayerPosition::St]);
    $service->registerPlayer($match, $outfield1, AttendanceStatus::Confirmed);
    $service->registerPlayer($match, $outfield2, AttendanceStatus::Confirmed);

    // GK confirms without team — should be substitute (no priority)
    $gk = Player::factory()->goalkeeper()->create(['club_id' => $match->club_id]);
    $attendance = $service->registerPlayer($match, $gk, AttendanceStatus::Confirmed);

    expect($attendance->role)->toBe(AttendanceRole::Substitute);
});

test('demoted player is the last confirmed non-GK on the team', function () {
    $match = FootballMatch::factory()->create(['max_players' => 6]); // 3 per team
    $service = new MatchService;

    // Fill team A with 3 outfield players
    $first = Player::factory()->create(['club_id' => $match->club_id, 'position' => PlayerPosition::Cb]);
    $second = Player::factory()->create(['club_id' => $match->club_id, 'position' => PlayerPosition::Cm]);
    $third = Player::factory()->create(['club_id' => $match->club_id, 'position' => PlayerPosition::St]);

    $service->registerPlayer($match, $first, AttendanceStatus::Confirmed, AttendanceTeam::A);
    $service->registerPlayer($match, $second, AttendanceStatus::Confirmed, AttendanceTeam::A);
    $service->registerPlayer($match, $third, AttendanceStatus::Confirmed, AttendanceTeam::A);

    // GK confirms — the third (last confirmed) should be demoted
    $gk = Player::factory()->goalkeeper()->create(['club_id' => $match->club_id]);
    $service->registerPlayer($match, $gk, AttendanceStatus::Confirmed, AttendanceTeam::A);

    expect(MatchAttendance::where('player_id', $first->id)->first()->role)->toBe(AttendanceRole::Starter)
        ->and(MatchAttendance::where('player_id', $second->id)->first()->role)->toBe(AttendanceRole::Starter)
        ->and(MatchAttendance::where('player_id', $third->id)->first()->role)->toBe(AttendanceRole::Substitute)
        ->and(MatchAttendance::where('player_id', $gk->id)->first()->role)->toBe(AttendanceRole::Starter);
});

test('recalculateRoles places GK as starter ahead of later outfield players', function () {
    $match = FootballMatch::factory()->create(['max_players' => 4]); // 2 per team
    $service = new MatchService;

    // Create 3 attendances on team A: 2 outfield then GK, all as starters
    $outfield1 = Player::factory()->create(['club_id' => $match->club_id, 'position' => PlayerPosition::Cm]);
    $outfield2 = Player::factory()->create(['club_id' => $match->club_id, 'position' => PlayerPosition::St]);
    $gk = Player::factory()->goalkeeper()->create(['club_id' => $match->club_id]);

    MatchAttendance::factory()->create([
        'match_id' => $match->id,
        'player_id' => $outfield1->id,
        'status' => AttendanceStatus::Confirmed,
        'team' => AttendanceTeam::A,
        'role' => AttendanceRole::Starter,
        'confirmed_at' => now(),
    ]);
    MatchAttendance::factory()->create([
        'match_id' => $match->id,
        'player_id' => $outfield2->id,
        'status' => AttendanceStatus::Confirmed,
        'team' => AttendanceTeam::A,
        'role' => AttendanceRole::Starter,
        'confirmed_at' => now()->addMinute(),
    ]);
    MatchAttendance::factory()->create([
        'match_id' => $match->id,
        'player_id' => $gk->id,
        'status' => AttendanceStatus::Confirmed,
        'team' => AttendanceTeam::A,
        'role' => AttendanceRole::Starter,
        'confirmed_at' => now()->addMinutes(2),
    ]);

    $service->recalculateRoles($match);

    // GK gets priority, first outfield keeps slot, second outfield becomes sub
    expect(MatchAttendance::where('player_id', $gk->id)->first()->role)->toBe(AttendanceRole::Starter)
        ->and(MatchAttendance::where('player_id', $outfield1->id)->first()->role)->toBe(AttendanceRole::Starter)
        ->and(MatchAttendance::where('player_id', $outfield2->id)->first()->role)->toBe(AttendanceRole::Substitute);
});

test('goalkeeper priority on team B does not affect team A', function () {
    $match = FootballMatch::factory()->create(['max_players' => 4]); // 2 per team
    $service = new MatchService;

    // Fill both teams
    $teamAPlayer1 = Player::factory()->create(['club_id' => $match->club_id, 'position' => PlayerPosition::Cm]);
    $teamAPlayer2 = Player::factory()->create(['club_id' => $match->club_id, 'position' => PlayerPosition::St]);
    $teamBPlayer1 = Player::factory()->create(['club_id' => $match->club_id, 'position' => PlayerPosition::Cb]);
    $teamBPlayer2 = Player::factory()->create(['club_id' => $match->club_id, 'position' => PlayerPosition::Cm]);

    $service->registerPlayer($match, $teamAPlayer1, AttendanceStatus::Confirmed, AttendanceTeam::A);
    $service->registerPlayer($match, $teamAPlayer2, AttendanceStatus::Confirmed, AttendanceTeam::A);
    $service->registerPlayer($match, $teamBPlayer1, AttendanceStatus::Confirmed, AttendanceTeam::B);
    $service->registerPlayer($match, $teamBPlayer2, AttendanceStatus::Confirmed, AttendanceTeam::B);

    // GK confirms on team B
    $gk = Player::factory()->goalkeeper()->create(['club_id' => $match->club_id]);
    $service->registerPlayer($match, $gk, AttendanceStatus::Confirmed, AttendanceTeam::B);

    // Team A players should remain starters
    expect(MatchAttendance::where('player_id', $teamAPlayer1->id)->first()->role)->toBe(AttendanceRole::Starter)
        ->and(MatchAttendance::where('player_id', $teamAPlayer2->id)->first()->role)->toBe(AttendanceRole::Starter);

    // GK is starter on team B, last team B player is demoted
    expect(MatchAttendance::where('player_id', $gk->id)->first()->role)->toBe(AttendanceRole::Starter)
        ->and(MatchAttendance::where('player_id', $teamBPlayer2->id)->first()->role)->toBe(AttendanceRole::Substitute);
});

// --- Substitute promotion tests ---

test('substitute is promoted to starter when a starter declines via registerPlayer', function () {
    $match = FootballMatch::factory()->create(['max_players' => 4, 'max_substitutes' => 4]); // 2 per team
    $service = new MatchService;

    // Fill team A: 2 starters + 1 substitute
    $starter1 = Player::factory()->create(['club_id' => $match->club_id, 'position' => PlayerPosition::Cm]);
    $starter2 = Player::factory()->create(['club_id' => $match->club_id, 'position' => PlayerPosition::St]);
    $sub = Player::factory()->create(['club_id' => $match->club_id, 'position' => PlayerPosition::Cb]);

    $service->registerPlayer($match, $starter1, AttendanceStatus::Confirmed, AttendanceTeam::A);
    $service->registerPlayer($match, $starter2, AttendanceStatus::Confirmed, AttendanceTeam::A);
    $service->registerPlayer($match, $sub, AttendanceStatus::Confirmed, AttendanceTeam::A);

    expect(MatchAttendance::where('player_id', $sub->id)->first()->role)->toBe(AttendanceRole::Substitute);

    // Starter 2 declines
    $service->registerPlayer($match, $starter2, AttendanceStatus::Declined);

    // Substitute should now be promoted to starter
    expect(MatchAttendance::where('player_id', $sub->id)->first()->role)->toBe(AttendanceRole::Starter)
        ->and(MatchAttendance::where('player_id', $starter1->id)->first()->role)->toBe(AttendanceRole::Starter)
        ->and(MatchAttendance::where('player_id', $starter2->id)->first()->role)->toBe(AttendanceRole::Pending);
});
