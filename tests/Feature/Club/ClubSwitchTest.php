<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\User;

test('user can switch to a club they belong to', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create([
        'club_id' => $club->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->post(route('clubs.switch', $club))
        ->assertRedirect(route('clubs.show', $club));
});

test('switch updates last_club_id and session', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create([
        'club_id' => $club->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->post(route('clubs.switch', $club))
        ->assertSessionHas('active_club_id', $club->id);

    expect($user->fresh()->last_club_id)->toBe($club->id);
});

test('user cannot switch to a club they dont belong to', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();

    $this->actingAs($user)
        ->post(route('clubs.switch', $club))
        ->assertForbidden();
});

test('guest cannot switch clubs', function () {
    $club = Club::factory()->create();

    $this->post(route('clubs.switch', $club))
        ->assertRedirect(route('login'));
});
