<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\Player;
use App\Models\User;

test('members can register players for a match', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $player->id,
            'status' => 'confirmed',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('match_attendances', [
        'match_id' => $match->id,
        'player_id' => $player->id,
        'status' => 'confirmed',
        'role' => 'starter',
    ]);
});

test('non-members cannot register players', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $player->id,
            'status' => 'confirmed',
        ])
        ->assertForbidden();
});

test('player can register with team choice', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $player->id,
            'status' => 'confirmed',
            'team' => 'a',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('match_attendances', [
        'match_id' => $match->id,
        'player_id' => $player->id,
        'status' => 'confirmed',
        'team' => 'a',
        'role' => 'starter',
    ]);
});

test('player can register without team choice', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $player->id,
            'status' => 'confirmed',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('match_attendances', [
        'match_id' => $match->id,
        'player_id' => $player->id,
        'status' => 'confirmed',
        'team' => null,
    ]);
});

test('cannot confirm player when team is full', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'max_players' => 2, // 1 per team
        'max_substitutes' => 2, // 1 sub per team
    ]);

    // Fill team A: 1 starter + 1 sub = full
    for ($i = 0; $i < 2; $i++) {
        $player = Player::factory()->create(['club_id' => $club->id]);
        MatchAttendance::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'status' => 'confirmed',
            'team' => 'a',
            'role' => $i === 0 ? 'starter' : 'substitute',
        ]);
    }

    $newPlayer = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $newPlayer->id,
            'status' => 'confirmed',
            'team' => 'a',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'El cupo del partido está lleno.');

    $this->assertDatabaseMissing('match_attendances', [
        'match_id' => $match->id,
        'player_id' => $newPlayer->id,
    ]);
});

test('can confirm player on team with room even when other team is full', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'max_players' => 4, // 2 per team
        'max_substitutes' => 2, // 1 sub per team
    ]);

    // Fill team A completely (2 starters + 1 sub)
    for ($i = 0; $i < 3; $i++) {
        $player = Player::factory()->create(['club_id' => $club->id]);
        MatchAttendance::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'status' => 'confirmed',
            'team' => 'a',
            'role' => $i < 2 ? 'starter' : 'substitute',
        ]);
    }

    // Team B should still accept players
    $newPlayer = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $newPlayer->id,
            'status' => 'confirmed',
            'team' => 'b',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('match_attendances', [
        'match_id' => $match->id,
        'player_id' => $newPlayer->id,
        'role' => 'starter',
        'team' => 'b',
    ]);
});

test('can still decline player when match is full', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'max_players' => 2,
        'max_substitutes' => 0,
    ]);

    for ($i = 0; $i < 2; $i++) {
        $player = Player::factory()->create(['club_id' => $club->id]);
        MatchAttendance::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'status' => 'confirmed',
        ]);
    }

    $newPlayer = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $newPlayer->id,
            'status' => 'declined',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('match_attendances', [
        'match_id' => $match->id,
        'player_id' => $newPlayer->id,
        'status' => 'declined',
    ]);
});
