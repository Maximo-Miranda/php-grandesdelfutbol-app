<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\Field;
use App\Models\User;
use App\Models\Venue;

test('admins can create fields', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $venue = Venue::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.venues.fields.store', [$club, $venue]), [
            'name' => 'Field A',
            'field_type' => '5v5',
            'surface_type' => 'Turf',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('fields', [
        'venue_id' => $venue->id,
        'name' => 'Field A',
        'field_type' => '5v5',
        'surface_type' => 'Turf',
    ]);
});

test('admins can update fields', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $venue = Venue::factory()->create(['club_id' => $club->id]);
    $field = Field::factory()->create(['venue_id' => $venue->id]);

    $this->actingAs($user)
        ->put(route('clubs.venues.fields.update', [$club, $venue, $field]), [
            'name' => 'Updated Field',
            'field_type' => '7v7',
            'is_active' => false,
        ])
        ->assertRedirect();

    $field->refresh();
    expect($field->name)->toBe('Updated Field')
        ->and($field->field_type->value)->toBe('7v7')
        ->and($field->is_active)->toBeFalse();
});
