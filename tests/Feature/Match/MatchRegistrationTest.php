<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
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
