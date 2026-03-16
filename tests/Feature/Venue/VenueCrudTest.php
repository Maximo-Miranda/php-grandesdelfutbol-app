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

test('admins can quick-create a venue with a field', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('clubs.venues.storeQuick', $club), [
            'name' => 'Cancha Papiros',
            'address' => 'Calle 50 # 20-10',
            'field_name' => 'Cancha 1 7v7 Sintetico',
            'field_type' => '7v7',
            'surface_type' => 'sintetico',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('venues', [
        'club_id' => $club->id,
        'name' => 'Cancha Papiros',
    ]);

    $venue = Venue::query()->where('name', 'Cancha Papiros')->first();
    $this->assertDatabaseHas('fields', [
        'venue_id' => $venue->id,
        'name' => 'Cancha 1 7v7 Sintetico',
        'field_type' => '7v7',
    ]);
});

test('admins can delete a venue', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $venue = Venue::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->delete(route('clubs.venues.destroy', [$club, $venue]))
        ->assertRedirect(route('clubs.venues.index', $club));

    $this->assertDatabaseMissing('venues', ['id' => $venue->id]);
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
