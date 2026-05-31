<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;

test('admins can auto-assign teams', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id, 'max_players' => 4]);

    $players = Player::factory()->count(4)->create(['club_id' => $club->id]);
    foreach ($players as $player) {
        MatchAttendance::factory()->create(['match_id' => $match->id, 'player_id' => $player->id]);
    }

    $this->actingAs($user)
        ->post(route('clubs.matches.autoAssign', [$club, $match]))
        ->assertRedirect();

    $match->load('attendances');
    expect($match->attendances->whereNotNull('team')->count())->toBe(4);
});

test('auto-assign starters are the earliest confirmations regardless of skill', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'max_players' => 4,
        'max_substitutes' => 2,
    ]);

    // 6 players: the 4 earliest are low-skill, the 2 latest are stars.
    // FIFO must win — stars should end up as substitutes.
    $earlyLowSkill = Player::factory()->count(4)->create([
        'club_id' => $club->id,
        'matches_played' => 5,
        'goals' => 0,
        'assists' => 0,
    ]);
    $lateStars = Player::factory()->count(2)->create([
        'club_id' => $club->id,
        'matches_played' => 20,
        'goals' => 30,
        'assists' => 20,
    ]);

    foreach ($earlyLowSkill as $i => $player) {
        MatchAttendance::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'confirmed_at' => now()->subMinutes(60 - $i),
        ]);
    }
    foreach ($lateStars as $i => $player) {
        MatchAttendance::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'confirmed_at' => now()->subMinutes(10 - $i),
        ]);
    }

    $this->actingAs($user)
        ->post(route('clubs.matches.autoAssign', [$club, $match]))
        ->assertRedirect();

    foreach ($earlyLowSkill as $player) {
        expect(MatchAttendance::where('player_id', $player->id)->first()->role->value)->toBe('starter');
    }
    foreach ($lateStars as $player) {
        expect(MatchAttendance::where('player_id', $player->id)->first()->role->value)->toBe('substitute');
    }
});

test('auto-assign honors player team preference from last completed match', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    // Past completed match where the players had a clear team
    $previousMatch = FootballMatch::factory()->completed()->create([
        'club_id' => $club->id,
        'scheduled_at' => now()->subWeek(),
    ]);

    // 4 players: 2 historically on team A, 2 on team B. Identical strong stats
    // keep skill scores balanced so the skill rebalance pass never overrides
    // the honored team preference (statless players get random scores, which
    // would spuriously trigger a swap and make this assertion flaky).
    $balancedStats = ['matches_played' => 20, 'goals' => 30, 'assists' => 30];
    $aPlayers = Player::factory()->count(2)->create(['club_id' => $club->id, ...$balancedStats]);
    $bPlayers = Player::factory()->count(2)->create(['club_id' => $club->id, ...$balancedStats]);

    foreach ($aPlayers as $player) {
        MatchAttendance::factory()->teamA()->starter()->create([
            'match_id' => $previousMatch->id,
            'player_id' => $player->id,
        ]);
    }
    foreach ($bPlayers as $player) {
        MatchAttendance::factory()->teamB()->starter()->create([
            'match_id' => $previousMatch->id,
            'player_id' => $player->id,
        ]);
    }

    // Current upcoming match — same 4 players confirm without team
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'max_players' => 4,
        'max_substitutes' => 0,
    ]);

    foreach ([...$aPlayers, ...$bPlayers] as $i => $player) {
        MatchAttendance::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'team' => null,
            'confirmed_at' => now()->subMinutes(60 - $i),
        ]);
    }

    $this->actingAs($user)
        ->post(route('clubs.matches.autoAssign', [$club, $match]))
        ->assertRedirect();

    foreach ($aPlayers as $player) {
        expect(MatchAttendance::where('player_id', $player->id)->where('match_id', $match->id)->first()->team->value)->toBe('a');
    }
    foreach ($bPlayers as $player) {
        expect(MatchAttendance::where('player_id', $player->id)->where('match_id', $match->id)->first()->team->value)->toBe('b');
    }
});

test('auto-assign rebalances when honoring all preferences would create large gap', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $previousMatch = FootballMatch::factory()->completed()->create([
        'club_id' => $club->id,
        'scheduled_at' => now()->subWeek(),
    ]);

    // All 4 players historically on team A — preference would put 4 on A and 0 on B
    // The capacity cap (max 2 per team) plus rebalance must split them anyway.
    $players = Player::factory()->count(4)->create(['club_id' => $club->id]);
    foreach ($players as $player) {
        MatchAttendance::factory()->teamA()->starter()->create([
            'match_id' => $previousMatch->id,
            'player_id' => $player->id,
        ]);
    }

    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'max_players' => 4,
        'max_substitutes' => 0,
    ]);

    foreach ($players as $i => $player) {
        MatchAttendance::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'team' => null,
            'confirmed_at' => now()->subMinutes(60 - $i),
        ]);
    }

    $this->actingAs($user)
        ->post(route('clubs.matches.autoAssign', [$club, $match]))
        ->assertRedirect();

    $match->load('attendances');
    expect($match->attendances->where('team', 'a')->count())->toBe(2)
        ->and($match->attendances->where('team', 'b')->count())->toBe(2);
});

test('roster wins over open-call preference on team-restricted matches', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $season = Season::factory()->create(['club_id' => $club->id]);
    $teamA = Team::factory()->create(['club_id' => $club->id, 'season_id' => $season->id]);
    $teamB = Team::factory()->create(['club_id' => $club->id, 'season_id' => $season->id]);

    $playerInRosterA = Player::factory()->create(['club_id' => $club->id]);
    $teamA->players()->attach($playerInRosterA->id);

    // Player has a strong open-call preference for team B from a past completed match
    $previousOpenCall = FootballMatch::factory()->completed()->create([
        'club_id' => $club->id,
        'team_a_id' => null,
        'team_b_id' => null,
        'scheduled_at' => now()->subWeek(),
    ]);
    MatchAttendance::factory()->teamB()->starter()->create([
        'match_id' => $previousOpenCall->id,
        'player_id' => $playerInRosterA->id,
    ]);

    // New match is team-restricted between teamA and teamB
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'team_a_id' => $teamA->id,
        'team_b_id' => $teamB->id,
        'season_id' => $season->id,
    ]);
    MatchAttendance::factory()->create([
        'match_id' => $match->id,
        'player_id' => $playerInRosterA->id,
        'team' => null,
        'confirmed_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('clubs.matches.autoAssign', [$club, $match]))
        ->assertRedirect();

    // Roster (team A) wins; open-call preference for B is ignored
    expect(MatchAttendance::where('player_id', $playerInRosterA->id)->where('match_id', $match->id)->first()->team->value)
        ->toBe('a');
});

test('swap is rejected on team-restricted matches', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $season = Season::factory()->create(['club_id' => $club->id]);
    $teamA = Team::factory()->create(['club_id' => $club->id, 'season_id' => $season->id]);
    $teamB = Team::factory()->create(['club_id' => $club->id, 'season_id' => $season->id]);

    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'team_a_id' => $teamA->id,
        'team_b_id' => $teamB->id,
        'season_id' => $season->id,
    ]);

    $a = MatchAttendance::factory()->teamA()->starter()->create(['match_id' => $match->id]);
    $b = MatchAttendance::factory()->teamB()->starter()->create(['match_id' => $match->id]);

    $this->actingAs($admin)
        ->post(route('clubs.matches.attendance.swap', [$club, $match]), [
            'source_attendance_id' => $a->id,
            'target_attendance_id' => $b->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($a->refresh()->team->value)->toBe('a')
        ->and($b->refresh()->team->value)->toBe('b');
});

test('auto-assign is a no-op once match has started', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->inProgress()->create([
        'club_id' => $club->id,
        'max_players' => 4,
    ]);

    $players = Player::factory()->count(4)->create(['club_id' => $club->id]);
    foreach ($players as $player) {
        MatchAttendance::factory()->teamA()->starter()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
        ]);
    }

    $this->actingAs($user)
        ->post(route('clubs.matches.autoAssign', [$club, $match]))
        ->assertRedirect();

    $match->load('attendances');
    expect($match->attendances->whereNotNull('team')->where('team', 'a')->count())->toBe(4);
});

test('auto-assign distributes starters and substitutes across both teams', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'max_players' => 4,
        'max_substitutes' => 2,
    ]);

    $players = Player::factory()->count(6)->create(['club_id' => $club->id]);
    foreach ($players as $i => $player) {
        MatchAttendance::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'confirmed_at' => now()->subMinutes(60 - $i),
        ]);
    }

    $this->actingAs($user)
        ->post(route('clubs.matches.autoAssign', [$club, $match]))
        ->assertRedirect();

    $match->load('attendances');
    $starters = $match->attendances->where('role', 'starter');
    $subs = $match->attendances->where('role', 'substitute');

    expect($starters->where('team', 'a')->count())->toBe(2)
        ->and($starters->where('team', 'b')->count())->toBe(2)
        ->and($subs->where('team', 'a')->count())->toBe(1)
        ->and($subs->where('team', 'b')->count())->toBe(1);
});

test('admins can update individual attendance', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);
    $attendance = MatchAttendance::factory()->create(['match_id' => $match->id]);

    $this->actingAs($user)
        ->patch(route('clubs.matches.attendance.update', [$club, $match, $attendance]), [
            'team' => 'a',
            'role' => 'starter',
        ])
        ->assertRedirect();

    $attendance->refresh();
    expect($attendance->team->value)->toBe('a');
});

test('admins can unconfirm a confirmed player', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);
    $attendance = MatchAttendance::factory()->starter()->teamA()->create(['match_id' => $match->id]);

    $this->actingAs($user)
        ->patch(route('clubs.matches.attendance.update', [$club, $match, $attendance]), [
            'status' => 'declined',
        ])
        ->assertRedirect();

    $attendance->refresh();
    expect($attendance->status->value)->toBe('declined')
        ->and($attendance->role->value)->toBe('pending')
        ->and($attendance->team)->toBeNull()
        ->and($attendance->confirmed_at)->toBeNull();
});

test('admins can remove a player from the match', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);
    $attendance = MatchAttendance::factory()->starter()->create(['match_id' => $match->id]);

    $this->actingAs($user)
        ->delete(route('clubs.matches.attendance.destroy', [$club, $match, $attendance]))
        ->assertRedirect();

    $this->assertDatabaseMissing('match_attendances', ['id' => $attendance->id]);
});

test('admins can reconfirm a declined player', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id, 'max_players' => 10]);
    $attendance = MatchAttendance::factory()->declined()->create(['match_id' => $match->id]);

    $this->actingAs($user)
        ->patch(route('clubs.matches.attendance.update', [$club, $match, $attendance]), [
            'status' => 'confirmed',
        ])
        ->assertRedirect();

    $attendance->refresh();
    expect($attendance->status->value)->toBe('confirmed')
        ->and($attendance->role->value)->toBe('starter')
        ->and($attendance->confirmed_at)->not->toBeNull();
});
