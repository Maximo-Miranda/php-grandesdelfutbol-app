<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\User;

test('users without clubs are redirected to club creation from club index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('clubs.index'))
        ->assertRedirect(route('clubs.create'));
});

test('users without clubs can access the club creation page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('clubs.create'))
        ->assertOk();
});

test('users without clubs can store a new club', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('clubs.store'), ['name' => 'My Club'])
        ->assertRedirect();
});

test('users without clubs can access settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk();
});

test('users without clubs can logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users with clubs are not redirected', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('clubs.show', $club))
        ->assertOk();
});

test('stale club context is cleared when user has no clubs', function () {
    $club = Club::factory()->create();
    $user = User::factory()->create(['last_club_id' => $club->id]);

    // Remove the membership so the user has a stale last_club_id
    $this->actingAs($user)
        ->get(route('clubs.create'))
        ->assertOk();

    expect($user->fresh()->last_club_id)->toBeNull();
});
