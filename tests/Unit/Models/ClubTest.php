<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\User;

test('club belongs to an owner', function () {
    $club = Club::factory()->create();

    expect($club->owner)->toBeInstanceOf(User::class);
});

test('club has many members', function () {
    $club = Club::factory()->create();
    ClubMember::factory()->count(3)->create(['club_id' => $club->id]);

    expect($club->members)->toHaveCount(3);
});

test('forUser scope returns only clubs where user is an approved member', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create([
        'club_id' => $club->id,
        'user_id' => $user->id,
        'status' => 'approved',
    ]);

    $otherClub = Club::factory()->create();

    expect(Club::query()->forUser($user)->get())->toHaveCount(1)
        ->and(Club::query()->forUser($user)->first()->id)->toBe($club->id);
});

test('forUser scope excludes pending members', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->pending()->create([
        'club_id' => $club->id,
        'user_id' => $user->id,
    ]);

    expect(Club::query()->forUser($user)->get())->toHaveCount(0);
});

test('club casts boolean fields correctly', function () {
    $club = Club::factory()->create([
        'is_invite_active' => true,
        'requires_approval' => true,
    ]);

    expect($club->is_invite_active)->toBeTrue()
        ->and($club->requires_approval)->toBeTrue();
});
