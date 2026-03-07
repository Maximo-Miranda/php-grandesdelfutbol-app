<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\User;

test('admins can view members list', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('clubs.members.index', $club))
        ->assertOk();
});

test('admins can approve pending members', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    $pendingMember = ClubMember::factory()->pending()->create(['club_id' => $club->id]);

    $this->actingAs($admin)
        ->patch(route('clubs.members.approve', [$club, $pendingMember]))
        ->assertRedirect();

    $pendingMember->refresh();
    expect($pendingMember->status->value)->toBe('approved');
});

test('admins can reject pending members', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    $pendingMember = ClubMember::factory()->pending()->create(['club_id' => $club->id]);

    $this->actingAs($admin)
        ->delete(route('clubs.members.reject', [$club, $pendingMember]))
        ->assertRedirect();

    $this->assertDatabaseMissing('club_members', ['id' => $pendingMember->id]);
});

test('owner can update member role to admin', function () {
    $owner = User::factory()->create();
    $club = Club::factory()->create(['owner_id' => $owner->id]);
    ClubMember::factory()->owner()->create(['club_id' => $club->id, 'user_id' => $owner->id]);
    $member = ClubMember::factory()->create(['club_id' => $club->id, 'role' => 'player']);

    $this->actingAs($owner)
        ->patch(route('clubs.members.updateRole', [$club, $member]), ['role' => 'admin'])
        ->assertRedirect();

    $member->refresh();
    expect($member->role->value)->toBe('admin');
});

test('owner can change admin role to player', function () {
    $owner = User::factory()->create();
    $club = Club::factory()->create(['owner_id' => $owner->id]);
    ClubMember::factory()->owner()->create(['club_id' => $club->id, 'user_id' => $owner->id]);
    $adminMember = ClubMember::factory()->admin()->create(['club_id' => $club->id]);

    $this->actingAs($owner)
        ->patch(route('clubs.members.updateRole', [$club, $adminMember]), ['role' => 'player'])
        ->assertRedirect();

    $adminMember->refresh();
    expect($adminMember->role->value)->toBe('player');
});

test('admin cannot change role of another admin', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    $otherAdmin = ClubMember::factory()->admin()->create(['club_id' => $club->id]);

    $this->actingAs($admin)
        ->patch(route('clubs.members.updateRole', [$club, $otherAdmin]), ['role' => 'player'])
        ->assertForbidden();
});

test('admin cannot promote player to admin', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    $member = ClubMember::factory()->create(['club_id' => $club->id, 'role' => 'player']);

    $this->actingAs($admin)
        ->patch(route('clubs.members.updateRole', [$club, $member]), ['role' => 'admin'])
        ->assertForbidden();
});

test('admin can change player role to player', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    $member = ClubMember::factory()->create(['club_id' => $club->id, 'role' => 'player']);

    $this->actingAs($admin)
        ->patch(route('clubs.members.updateRole', [$club, $member]), ['role' => 'player'])
        ->assertRedirect();
});

test('admins can remove player members', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    $member = ClubMember::factory()->create(['club_id' => $club->id]);

    $this->actingAs($admin)
        ->delete(route('clubs.members.remove', [$club, $member]))
        ->assertRedirect();

    $this->assertDatabaseMissing('club_members', ['id' => $member->id]);
});

test('admin cannot remove another admin', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    $otherAdmin = ClubMember::factory()->admin()->create(['club_id' => $club->id]);

    $this->actingAs($admin)
        ->delete(route('clubs.members.remove', [$club, $otherAdmin]))
        ->assertForbidden();

    $this->assertDatabaseHas('club_members', ['id' => $otherAdmin->id]);
});

test('admins cannot remove the owner', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    $ownerMember = ClubMember::factory()->owner()->create(['club_id' => $club->id]);

    $this->actingAs($admin)
        ->delete(route('clubs.members.remove', [$club, $ownerMember]))
        ->assertForbidden();

    $this->assertDatabaseHas('club_members', ['id' => $ownerMember->id]);
});

test('owner can remove an admin', function () {
    $owner = User::factory()->create();
    $club = Club::factory()->create(['owner_id' => $owner->id]);
    ClubMember::factory()->owner()->create(['club_id' => $club->id, 'user_id' => $owner->id]);
    $adminMember = ClubMember::factory()->admin()->create(['club_id' => $club->id]);

    $this->actingAs($owner)
        ->delete(route('clubs.members.remove', [$club, $adminMember]))
        ->assertRedirect();

    $this->assertDatabaseMissing('club_members', ['id' => $adminMember->id]);
});

test('admin can leave the club', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    $membership = ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);

    $this->actingAs($admin)
        ->post(route('clubs.leave', $club))
        ->assertRedirect(route('clubs.index'));

    $this->assertDatabaseMissing('club_members', ['id' => $membership->id]);
});

test('player can leave the club', function () {
    $player = User::factory()->create();
    $club = Club::factory()->create();
    $membership = ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $player->id]);

    $this->actingAs($player)
        ->post(route('clubs.leave', $club))
        ->assertRedirect(route('clubs.index'));

    $this->assertDatabaseMissing('club_members', ['id' => $membership->id]);
});

test('owner cannot leave the club', function () {
    $owner = User::factory()->create();
    $club = Club::factory()->create(['owner_id' => $owner->id]);
    ClubMember::factory()->owner()->create(['club_id' => $club->id, 'user_id' => $owner->id]);

    $this->actingAs($owner)
        ->post(route('clubs.leave', $club))
        ->assertForbidden();
});
