<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\User;

test('guests see the welcome page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Welcome'));
});

test('authenticated user with last_club_id is redirected to that club', function () {
    $club = Club::factory()->create();
    $user = User::factory()->create(['last_club_id' => $club->id]);
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(route('clubs.show', $club));
});

test('authenticated user without last_club_id but with clubs is redirected to their club', function () {
    $club = Club::factory()->create();
    $user = User::factory()->create(['last_club_id' => null]);
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(route('clubs.show', $club));
});

test('authenticated user with stale last_club_id is redirected to clubs index', function () {
    $staleClub = Club::factory()->create();
    $user = User::factory()->create(['last_club_id' => $staleClub->id]);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(route('clubs.index'));
});

test('authenticated user with no clubs is redirected to clubs index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(route('clubs.index'));
});
