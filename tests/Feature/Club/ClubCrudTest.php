<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\User;

test('guests are redirected to login when viewing clubs', function () {
    $this->get(route('clubs.index'))->assertRedirect(route('login'));
});

test('users without clubs are redirected to club creation from index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('clubs.index'))
        ->assertRedirect(route('clubs.create'));
});

test('users with clubs can view clubs index', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('clubs.index'))
        ->assertOk();
});

test('authenticated users can view create club form', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('clubs.create'))
        ->assertOk();
});

test('authenticated users can create a club', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('clubs.store'), [
            'name' => 'My Football Club',
            'description' => 'Best club ever',
            'requires_approval' => false,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('clubs', [
        'name' => 'My Football Club',
        'owner_id' => $user->id,
    ]);

    $this->assertDatabaseHas('club_members', [
        'user_id' => $user->id,
        'role' => 'owner',
        'status' => 'approved',
    ]);
});

test('club creation validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('clubs.store'), [])
        ->assertSessionHasErrors(['name']);
});

test('club creation validates name length', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('clubs.store'), ['name' => 'A'])
        ->assertSessionHasErrors(['name']);
});

test('club members can view a club', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create([
        'club_id' => $club->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('clubs.show', $club))
        ->assertOk();
});

test('admins can view the edit form', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create([
        'club_id' => $club->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('clubs.edit', $club))
        ->assertOk();
});

test('admins can update a club', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create([
        'club_id' => $club->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->put(route('clubs.update', $club), [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'requires_approval' => true,
            'is_invite_active' => true,
        ])
        ->assertRedirect();

    $club->refresh();
    expect($club->name)->toBe('Updated Name')
        ->and($club->requires_approval)->toBeTrue()
        ->and($club->is_invite_active)->toBeTrue();
});
