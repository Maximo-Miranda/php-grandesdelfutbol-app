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

test('regular members cannot update other players', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id, 'role' => 'player']);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->put(route('clubs.players.update', [$club, $player]), ['name' => 'Hack'])
        ->assertForbidden();
});

test('players can update their own profile', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id, 'role' => 'player']);
    $player = Player::factory()->create(['club_id' => $club->id, 'user_id' => $user->id, 'name' => 'Old Name']);

    $this->actingAs($user)
        ->put(route('clubs.players.update', [$club, $player]), [
            'name' => 'New Name',
            'position' => null,
            'jersey_number' => null,
        ])
        ->assertRedirect(route('clubs.players.show', [$club, $player]));

    expect($player->fresh()->name)->toBe('New Name');
});

test('players cannot set is_active on their own profile', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id, 'role' => 'player']);
    $player = Player::factory()->create(['club_id' => $club->id, 'user_id' => $user->id, 'is_active' => true]);

    $this->actingAs($user)
        ->put(route('clubs.players.update', [$club, $player]), [
            'name' => $player->name,
            'position' => null,
            'jersey_number' => null,
            'is_active' => false,
        ])
        ->assertRedirect();

    expect($player->fresh()->is_active)->toBeTrue();
});

test('regular members cannot delete players', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id, 'role' => 'player']);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->delete(route('clubs.players.destroy', [$club, $player]))
        ->assertForbidden();

    $this->assertDatabaseHas('players', ['id' => $player->id]);
});

test('players cannot delete their own player profile', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id, 'role' => 'player']);
    $player = Player::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->delete(route('clubs.players.destroy', [$club, $player]))
        ->assertForbidden();

    $this->assertDatabaseHas('players', ['id' => $player->id]);
});

test('non-admins cannot set user_id on player update', function () {
    $user = User::factory()->create();
    $targetUser = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id, 'role' => 'player']);
    $player = Player::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->put(route('clubs.players.update', [$club, $player]), [
            'name' => $player->name,
            'user_id' => $targetUser->id,
        ])
        ->assertRedirect();

    expect($player->fresh()->user_id)->toBe($user->id);
});

test('admins can update any player including is_active', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $admin->id, 'role' => 'admin']);
    $player = Player::factory()->create(['club_id' => $club->id, 'is_active' => true]);

    $this->actingAs($admin)
        ->put(route('clubs.players.update', [$club, $player]), [
            'name' => 'Admin Changed',
            'position' => null,
            'jersey_number' => null,
            'is_active' => false,
        ])
        ->assertRedirect();

    $player->refresh();
    expect($player->name)->toBe('Admin Changed');
    expect($player->is_active)->toBeFalse();
});
