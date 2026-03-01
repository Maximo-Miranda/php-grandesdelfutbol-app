<?php

use App\Models\Club;
use App\Models\User;

test('users can view join page for active invite link', function () {
    $club = Club::factory()->withInviteActive()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('clubs.join', $club->invite_token))
        ->assertOk();
});

test('users cannot view join page for inactive invite link', function () {
    $club = Club::factory()->create(['is_invite_active' => false]);

    $this->actingAs(User::factory()->create())
        ->get(route('clubs.join', $club->invite_token))
        ->assertNotFound();
});

test('users can join a club via invite link', function () {
    $club = Club::factory()->withInviteActive()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('clubs.join.store', $club->invite_token))
        ->assertRedirect();

    $this->assertDatabaseHas('club_members', [
        'club_id' => $club->id,
        'user_id' => $user->id,
        'status' => 'approved',
    ]);
});

test('joining a club with approval creates pending member', function () {
    $club = Club::factory()->withInviteActive()->withApproval()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('clubs.join.store', $club->invite_token))
        ->assertRedirect();

    $this->assertDatabaseHas('club_members', [
        'club_id' => $club->id,
        'user_id' => $user->id,
        'status' => 'pending',
    ]);
});
