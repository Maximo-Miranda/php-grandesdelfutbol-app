<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\User;

test('login always redirects to dashboard', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));
});

test('middleware sets session from last_club_id on first request after login', function () {
    $club = Club::factory()->create();
    $user = User::factory()->create(['last_club_id' => $club->id]);
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSessionHas('active_club_id', $club->id);
});

test('middleware repairs session when user is no longer a member of active club', function () {
    $oldClub = Club::factory()->create();
    $currentClub = Club::factory()->create();
    $user = User::factory()->create(['last_club_id' => $oldClub->id]);
    ClubMember::factory()->create(['club_id' => $currentClub->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->withSession(['active_club_id' => $oldClub->id])
        ->get(route('dashboard'))
        ->assertSessionHas('active_club_id', $currentClub->id);
});

test('middleware clears stale context when user has no clubs', function () {
    $club = Club::factory()->create();
    $user = User::factory()->create(['last_club_id' => $club->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSessionMissing('active_club_id');

    expect($user->fresh()->last_club_id)->toBeNull();
});

test('switching club updates last_club_id on user', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    expect($user->last_club_id)->toBeNull();

    $this->actingAs($user)->post(route('clubs.switch', $club));

    $user->refresh();
    expect($user->last_club_id)->toBe($club->id);
});
