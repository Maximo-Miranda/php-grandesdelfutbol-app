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

test('can create player with same jersey number as another in same club', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    Player::factory()->create(['club_id' => $club->id, 'jersey_number' => 9]);

    $this->actingAs($user)
        ->post(route('clubs.players.store', $club), [
            'name' => 'Another Player',
            'position' => 'ST',
            'jersey_number' => 9,
        ])
        ->assertRedirect();

    expect(Player::where('club_id', $club->id)->where('jersey_number', 9)->count())->toBe(2);
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

test('admins can delete players', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($admin)
        ->delete(route('clubs.players.destroy', [$club, $player]))
        ->assertRedirect(route('clubs.players.index', $club));

    $this->assertDatabaseMissing('players', ['id' => $player->id]);
});

test('admins can associate a user to a player', function () {
    $admin = User::factory()->create();
    $targetUser = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $targetUser->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($admin)
        ->put(route('clubs.players.update', [$club, $player]), [
            'name' => $player->name,
            'user_id' => $targetUser->id,
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($player->fresh()->user_id)->toBe($targetUser->id);
});

test('admins can disassociate a user from a player', function () {
    $admin = User::factory()->create();
    $targetUser = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $targetUser->id]);
    $player = Player::factory()->create(['club_id' => $club->id, 'user_id' => $targetUser->id]);

    $this->actingAs($admin)
        ->put(route('clubs.players.update', [$club, $player]), [
            'name' => $player->name,
            'user_id' => null,
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($player->fresh()->user_id)->toBeNull();
});

test('edit page shows available users for admin', function () {
    $admin = User::factory()->create();
    $availableUser = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $availableUser->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($admin)
        ->get(route('clubs.players.edit', [$club, $player]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/players/Edit')
            ->has('availableUsers', 2) // admin + availableUser (both approved, neither assigned)
            ->where('isAdmin', true)
        );
});

test('edit page shows all members including those with players', function () {
    $admin = User::factory()->create();
    $assignedUser = User::factory()->create();
    $freeUser = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $assignedUser->id]);
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $freeUser->id]);
    Player::factory()->create(['club_id' => $club->id, 'user_id' => $assignedUser->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($admin)
        ->get(route('clubs.players.edit', [$club, $player]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('availableUsers', 3) // admin + assignedUser + freeUser (all shown)
            ->where('availableUsers.0.has_player', fn ($v) => is_bool($v))
        );
});
