<?php

use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\ClubMember;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

test('admins can send invitations', function () {
    Notification::fake();
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('clubs.invitations.store', $club), ['email' => 'new@example.com'])
        ->assertRedirect();

    $this->assertDatabaseHas('club_invitations', [
        'club_id' => $club->id,
        'email' => 'new@example.com',
    ]);
});

test('regular members cannot send invitations', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id, 'role' => 'player']);

    $this->actingAs($user)
        ->post(route('clubs.invitations.store', $club), ['email' => 'new@example.com'])
        ->assertForbidden();
});

test('authenticated users can accept a valid invitation', function () {
    $user = User::factory()->create();
    $invitation = ClubInvitation::factory()->create(['email' => $user->email]);

    $this->actingAs($user)
        ->get(route('invitations.accept', $invitation->token))
        ->assertRedirect();

    $this->assertDatabaseHas('club_members', [
        'club_id' => $invitation->club_id,
        'user_id' => $user->id,
    ]);
});

test('expired invitations cannot be accepted', function () {
    $user = User::factory()->create();
    $invitation = ClubInvitation::factory()->expired()->create();

    $this->actingAs($user)
        ->get(route('invitations.accept', $invitation->token))
        ->assertNotFound();
});
