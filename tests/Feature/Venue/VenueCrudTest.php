<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\User;
use App\Models\Venue;

test('club members can view venues', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('clubs.venues.index', $club))
        ->assertOk();
});

test('admins can create venues', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('clubs.venues.store', $club), [
            'name' => 'Main Stadium',
            'address' => '123 Main St',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('venues', [
        'club_id' => $club->id,
        'name' => 'Main Stadium',
        'address' => '123 Main St',
    ]);
});

test('admins can update venues', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $venue = Venue::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->put(route('clubs.venues.update', [$club, $venue]), [
            'name' => 'Updated Venue',
            'is_active' => false,
        ])
        ->assertRedirect();

    $venue->refresh();
    expect($venue->name)->toBe('Updated Venue')
        ->and($venue->is_active)->toBeFalse();
});

test('members can view a venue', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $venue = Venue::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->get(route('clubs.venues.show', [$club, $venue]))
        ->assertOk();
});
