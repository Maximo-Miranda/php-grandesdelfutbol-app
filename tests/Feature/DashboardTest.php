<?php

use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('clubs.index'))
        ->assertRedirect(route('login'));
});

test('dashboard redirects to last club', function () {
    $club = Club::factory()->create();
    $user = User::factory()->create(['last_club_id' => $club->id]);
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('clubs.show', $club));
});

test('dashboard redirects to clubs index when user has no clubs', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('clubs.index'));
});

test('users with no clubs are redirected to club creation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('clubs.index'))
        ->assertRedirect(route('clubs.create'));
});

test('users with no clubs but a pending invitation are redirected to invitation', function () {
    $user = User::factory()->create();
    $invitation = ClubInvitation::factory()->create(['email' => $user->email]);

    $this->actingAs($user)
        ->get(route('clubs.index'))
        ->assertRedirect(route('invitations.show', $invitation->token));
});

test('clubs index renders for users with clubs', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('clubs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/Index')
            ->has('clubs', 1)
            ->has('pendingInvitations')
        );
});

test('clubs index includes next upcoming match', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    FootballMatch::factory()->count(3)->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->get(route('clubs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/Index')
            ->has('nextMatch')
        );
});

test('clubs index includes pending invitations for the user', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $otherClub = Club::factory()->create();
    ClubInvitation::factory()->create(['email' => $user->email, 'club_id' => $otherClub->id]);

    $this->actingAs($user)
        ->get(route('clubs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/Index')
            ->has('pendingInvitations', 1)
        );
});
