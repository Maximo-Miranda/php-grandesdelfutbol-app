<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\User;
use App\Models\Venue;

test('non-members cannot view venues', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();

    $this->actingAs($user)
        ->get(route('clubs.venues.index', $club))
        ->assertForbidden();
});

test('regular members cannot create venues', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id, 'role' => 'player']);

    $this->actingAs($user)
        ->post(route('clubs.venues.store', $club), ['name' => 'Test Venue'])
        ->assertForbidden();
});

test('regular members cannot update venues', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id, 'role' => 'player']);
    $venue = Venue::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->put(route('clubs.venues.update', [$club, $venue]), ['name' => 'Hack'])
        ->assertForbidden();
});
