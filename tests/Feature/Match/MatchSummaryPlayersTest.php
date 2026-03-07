<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\Player;
use App\Models\User;

test('summary route passes players for admin', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->get(route('clubs.matches.summary', [$club, $match]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/matches/Summary')
            ->has('players', 1)
        );
});

test('summary route does not pass players for non-admin', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id, 'role' => 'player']);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->get(route('clubs.matches.summary', [$club, $match]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/matches/Summary')
            ->has('players', 0)
        );
});

test('admin can add player to completed match', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
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
    ]);
});

test('admin can add player to completed match even when slots are full', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create([
        'club_id' => $club->id,
        'max_players' => 2,
        'max_substitutes' => 0,
    ]);

    $existingPlayers = Player::factory()->count(2)->create(['club_id' => $club->id]);
    foreach ($existingPlayers as $p) {
        MatchAttendance::factory()->create([
            'match_id' => $match->id,
            'player_id' => $p->id,
            'status' => 'confirmed',
            'team' => 'a',
        ]);
    }

    $newPlayer = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $newPlayer->id,
            'status' => 'confirmed',
            'team' => 'b',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('match_attendances', [
        'match_id' => $match->id,
        'player_id' => $newPlayer->id,
        'status' => 'confirmed',
        'team' => 'b',
    ]);
});

test('non-admin cannot add player to completed match', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id, 'role' => 'player']);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $player->id,
            'status' => 'confirmed',
            'team' => 'a',
        ])
        ->assertForbidden();
});

test('admin can remove player from completed match', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);
    $attendance = MatchAttendance::factory()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
        'status' => 'confirmed',
        'team' => 'a',
    ]);

    $this->actingAs($user)
        ->delete(route('clubs.matches.attendance.destroy', [$club, $match, $attendance]))
        ->assertRedirect();

    $this->assertDatabaseMissing('match_attendances', [
        'id' => $attendance->id,
    ]);
});
