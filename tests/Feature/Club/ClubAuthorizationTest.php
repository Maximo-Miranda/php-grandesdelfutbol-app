<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\User;

test('non-members cannot view a club', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();

    $this->actingAs($user)
        ->get(route('clubs.show', $club))
        ->assertForbidden();
});

test('pending members cannot view a club', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->pending()->create([
        'club_id' => $club->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('clubs.show', $club))
        ->assertForbidden();
});

test('regular members cannot edit a club', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create([
        'club_id' => $club->id,
        'user_id' => $user->id,
        'role' => 'player',
    ]);

    $this->actingAs($user)
        ->get(route('clubs.edit', $club))
        ->assertForbidden();
});

test('regular members cannot update a club', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create([
        'club_id' => $club->id,
        'user_id' => $user->id,
        'role' => 'player',
    ]);

    $this->actingAs($user)
        ->put(route('clubs.update', $club), ['name' => 'Hack'])
        ->assertForbidden();
});

test('owners can update a club', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create(['owner_id' => $user->id]);
    ClubMember::factory()->owner()->create([
        'club_id' => $club->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->put(route('clubs.update', $club), [
            'name' => 'Owner Update',
            'requires_approval' => false,
            'is_invite_active' => false,
        ])
        ->assertRedirect();
});

test('club index only shows clubs user is a member of', function () {
    $user = User::factory()->create();
    $memberClub = Club::factory()->create();
    ClubMember::factory()->create([
        'club_id' => $memberClub->id,
        'user_id' => $user->id,
    ]);
    Club::factory()->create(); // club user is not a member of

    $this->actingAs($user)
        ->get(route('clubs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/Index')
            ->has('clubs', 1)
        );
});
