<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\Player;
use App\Models\User;

test('non-members cannot view players', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();

    $this->actingAs($user)
        ->get(route('clubs.players.index', $club))
        ->assertForbidden();
});

test('regular members cannot create players', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id, 'role' => 'player']);

    $this->actingAs($user)
        ->post(route('clubs.players.store', $club), ['name' => 'Test'])
        ->assertForbidden();
});

test('regular members cannot update players', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id, 'role' => 'player']);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->put(route('clubs.players.update', [$club, $player]), ['name' => 'Hack'])
        ->assertForbidden();
});
