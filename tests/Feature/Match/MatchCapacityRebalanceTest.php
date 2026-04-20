<?php

use App\Enums\AttendanceRole;
use App\Enums\AttendanceStatus;
use App\Enums\AttendanceTeam;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\Player;
use App\Models\User;

test('reducing max_players demotes excess confirmed players to waitlist', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    // 7v7 match: 14 starters total (7 per team), no subs
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'max_players' => 14,
        'max_substitutes' => 0,
    ]);

    // 7 confirmed players on team A (all starters)
    $players = Player::factory()->count(7)->create(['club_id' => $club->id]);
    foreach ($players as $i => $player) {
        MatchAttendance::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'status' => AttendanceStatus::Confirmed,
            'role' => AttendanceRole::Starter,
            'team' => AttendanceTeam::A,
            'confirmed_at' => now()->subMinutes(60 - $i), // oldest first
        ]);
    }

    expect($match->attendances()->where('status', AttendanceStatus::Confirmed)->count())->toBe(7);

    // Admin shrinks to 5v5 (10 max, 5 per team)
    $this->actingAs($user)
        ->put(route('clubs.matches.update', [$club, $match]), [
            'title' => $match->title,
            'scheduled_at' => $match->scheduled_at->toIso8601String(),
            'duration_minutes' => $match->duration_minutes,
            'arrival_minutes' => $match->arrival_minutes,
            'max_players' => 10,
            'max_substitutes' => 0,
            'registration_opens_hours' => $match->registration_opens_hours,
        ])
        ->assertRedirect();

    $confirmed = $match->attendances()->where('status', AttendanceStatus::Confirmed)->where('team', AttendanceTeam::A)->orderBy('confirmed_at')->get();
    $waitlisted = $match->attendances()->where('status', AttendanceStatus::Waitlisted)->where('team', AttendanceTeam::A)->get();

    expect($confirmed)->toHaveCount(5); // first 5 stay
    expect($waitlisted)->toHaveCount(2); // last 2 go to waitlist
});

test('increasing max_players promotes waitlisted players to confirmed', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    // 5v5 match
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'max_players' => 10,
        'max_substitutes' => 0,
    ]);

    // 5 confirmed + 2 waitlisted on team A
    $confirmedPlayers = Player::factory()->count(5)->create(['club_id' => $club->id]);
    foreach ($confirmedPlayers as $i => $player) {
        MatchAttendance::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'status' => AttendanceStatus::Confirmed,
            'role' => AttendanceRole::Starter,
            'team' => AttendanceTeam::A,
            'confirmed_at' => now()->subMinutes(60 - $i),
        ]);
    }
    $waitlistedPlayers = Player::factory()->count(2)->create(['club_id' => $club->id]);
    foreach ($waitlistedPlayers as $i => $player) {
        MatchAttendance::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'status' => AttendanceStatus::Waitlisted,
            'role' => AttendanceRole::Pending,
            'team' => AttendanceTeam::A,
            'confirmed_at' => now()->subMinutes(20 - $i),
        ]);
    }

    // Expand to 7v7
    $this->actingAs($user)
        ->put(route('clubs.matches.update', [$club, $match]), [
            'title' => $match->title,
            'scheduled_at' => $match->scheduled_at->toIso8601String(),
            'duration_minutes' => $match->duration_minutes,
            'arrival_minutes' => $match->arrival_minutes,
            'max_players' => 14,
            'max_substitutes' => 0,
            'registration_opens_hours' => $match->registration_opens_hours,
        ])
        ->assertRedirect();

    $confirmed = $match->attendances()->where('status', AttendanceStatus::Confirmed)->where('team', AttendanceTeam::A)->count();
    $waitlisted = $match->attendances()->where('status', AttendanceStatus::Waitlisted)->where('team', AttendanceTeam::A)->count();

    expect($confirmed)->toBe(7); // all promoted
    expect($waitlisted)->toBe(0);
});

test('rebalance keeps a goalkeeper as starter even if they registered late', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'max_players' => 22, // 11v11, plenty of room initially
        'max_substitutes' => 0,
    ]);

    // 11 outfield players on team A (all confirmed, all starters) confirmed early
    $outfielders = Player::factory()->count(11)->create(['club_id' => $club->id, 'position' => 'CM']);
    foreach ($outfielders as $i => $player) {
        MatchAttendance::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'status' => AttendanceStatus::Confirmed,
            'role' => AttendanceRole::Starter,
            'team' => AttendanceTeam::A,
            'confirmed_at' => now()->subHours(5)->addMinutes($i),
        ]);
    }

    // GK registers LAST — much later than the outfielders
    $goalkeeper = Player::factory()->create(['club_id' => $club->id, 'position' => 'GK']);
    MatchAttendance::factory()->create([
        'match_id' => $match->id,
        'player_id' => $goalkeeper->id,
        'status' => AttendanceStatus::Confirmed,
        'role' => AttendanceRole::Starter,
        'team' => AttendanceTeam::A,
        'confirmed_at' => now()->subMinutes(5),
    ]);

    // Shrink to 12 total (6 per team) — only 6 confirmed slots on team A
    $this->actingAs($user)
        ->put(route('clubs.matches.update', [$club, $match]), [
            'title' => $match->title,
            'scheduled_at' => $match->scheduled_at->toIso8601String(),
            'duration_minutes' => $match->duration_minutes,
            'arrival_minutes' => $match->arrival_minutes,
            'max_players' => 12,
            'max_substitutes' => 0,
            'registration_opens_hours' => $match->registration_opens_hours,
        ])
        ->assertRedirect();

    // GK must still be confirmed as Starter (GK priority overrides confirmed_at order)
    $gkAttendance = $match->attendances()->where('player_id', $goalkeeper->id)->first();
    expect($gkAttendance->status)->toBe(AttendanceStatus::Confirmed);
    expect($gkAttendance->role)->toBe(AttendanceRole::Starter);

    // 5 early-registering outfielders stay confirmed, the other 6 go to waitlist
    expect($match->attendances()->where('team', AttendanceTeam::A)->where('status', AttendanceStatus::Confirmed)->count())->toBe(6);
    expect($match->attendances()->where('team', AttendanceTeam::A)->where('status', AttendanceStatus::Waitlisted)->count())->toBe(6);
});

test('changing only field_id without changing max_players does not rebalance', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $match = FootballMatch::factory()->create(['club_id' => $club->id, 'max_players' => 10, 'max_substitutes' => 4]);
    $players = Player::factory()->count(5)->create(['club_id' => $club->id]);
    foreach ($players as $i => $player) {
        MatchAttendance::factory()->create([
            'match_id' => $match->id, 'player_id' => $player->id,
            'status' => AttendanceStatus::Confirmed, 'role' => AttendanceRole::Starter,
            'team' => AttendanceTeam::A, 'confirmed_at' => now()->subMinutes(60 - $i),
        ]);
    }

    // Update without changing capacity
    $this->actingAs($user)
        ->put(route('clubs.matches.update', [$club, $match]), [
            'title' => 'Nuevo titulo',
            'scheduled_at' => $match->scheduled_at->toIso8601String(),
            'duration_minutes' => $match->duration_minutes,
            'arrival_minutes' => $match->arrival_minutes,
            'max_players' => 10,
            'max_substitutes' => 4,
            'registration_opens_hours' => $match->registration_opens_hours,
        ])
        ->assertRedirect();

    expect($match->fresh()->title)->toBe('Nuevo titulo');
    expect($match->attendances()->where('status', AttendanceStatus::Confirmed)->count())->toBe(5);
});
