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

test('admins can update member roles', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    $member = ClubMember::factory()->create(['club_id' => $club->id, 'role' => 'player']);

    $this->actingAs($admin)
        ->patch(route('clubs.members.updateRole', [$club, $member]), ['role' => 'admin'])
        ->assertRedirect();

    $member->refresh();
    expect($member->role->value)->toBe('admin');
});

test('admins can remove non-owner members', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    $member = ClubMember::factory()->create(['club_id' => $club->id]);

    $this->actingAs($admin)
        ->delete(route('clubs.members.remove', [$club, $member]))
        ->assertRedirect();

    $this->assertDatabaseMissing('club_members', ['id' => $member->id]);
});

test('admins cannot remove the owner', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    $ownerMember = ClubMember::factory()->owner()->create(['club_id' => $club->id]);

    $this->actingAs($admin)
        ->delete(route('clubs.members.remove', [$club, $ownerMember]))
        ->assertRedirect();

    $this->assertDatabaseHas('club_members', ['id' => $ownerMember->id]);
});
