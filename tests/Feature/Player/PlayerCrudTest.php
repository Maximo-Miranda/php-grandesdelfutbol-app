<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\Player;
use App\Models\User;

test('club members can view players', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('clubs.players.index', $club))
        ->assertOk();
});

test('admins can create players', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('clubs.players.store', $club), [
            'name' => 'John Doe',
            'position' => 'ST',
            'jersey_number' => 9,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('players', [
        'club_id' => $club->id,
        'name' => 'John Doe',
        'position' => 'ST',
        'jersey_number' => 9,
    ]);
});

test('admins can update players', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->put(route('clubs.players.update', [$club, $player]), [
            'name' => 'Updated Name',
            'position' => 'GK',
            'is_active' => false,
        ])
        ->assertRedirect();

    $player->refresh();
    expect($player->name)->toBe('Updated Name')
        ->and($player->is_active)->toBeFalse();
});

test('members can view a player', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->get(route('clubs.players.show', [$club, $player]))
        ->assertOk();
});
